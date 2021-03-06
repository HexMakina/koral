<?php

namespace HexMakina\koral\Controllers;

use HexMakina\Tempus\Dato;
use \HexMakina\kadro\Auth\Permission;

// Dato dependency only for export feature, move to Report?

class Customer extends \HexMakina\kadro\Controllers\ORM
{
    use \HexMakina\kadro\Controllers\Abilities\Traceable;
    use \HexMakina\koral\Controllers\Abilities\DetectCustomer;

    public function prepare()
    {
        parent::prepare();
        $this->get('HexMakina\BlackBox\StateAgentInterface')->resetFilters('service_abbrev');
    }

    public function dashboard()
    {
        return $this->dashboard_listing();
    }

    public function edit_alias()
    {
        $customer = $this->modelClassName()::one($this->router()->params($this->modelPrefix('id')));
        $alias = is_null($this->router()->params('alias_id')) ?
        $this->modelClassName()::make_alias_of($customer) :
        $this->modelClassName()::one($this->router()->params('alias_id'));

        $this->viewport('form_model', $alias);
        $this->viewport($this->modelPrefix('original'), $customer);
        $this->routeBack($customer);
    }

    public function edit()
    {
        parent::edit();

        if ($this->router()->submits() && !empty($this->router()->submitted()['alias_of'])) {
            $this->router()->hop($this->modelPrefix('new_alias'), [$this->modelPrefix('id') => $this->router()->submitted()['alias_of']]);
        }

        if ($this->formModel()->is_alias()) {
            $this->router()->hop($this->modelPrefix('edit_alias'), ['alias_id' => $this->formModel()->getId(), $this->modelPrefix('id') => $this->formModel()->get('alias_of')]);
        }

        if (!$this->formModel()->isNew()) {
            $this->viewport($this->modelPrefix('aliases'), $this->formModel()->listing_aliases());
        }

        if (empty($this->formModel()->get('original_name'))) {
            if (!is_null($this->formModel()->get('alias_of')) && !is_null($original_customer = $this->modelClassName()::exists($this->formModel()->get('alias_of')))) {
                $this->formModel()->set('original_name', $original_customer->get('name'));
            } else {
                $this->formModel()->set('original_name', '');
            }
        }

        $this->related_listings();
    }

    protected function related_listings($customer = null)
    {
        $customer = $customer ?? $this->load_model;

        if (is_null($customer) || $customer->isNew()) {
            return [];
        }

        $related_listings = [];

        $load_by_model = [$this->modelPrefix() => $customer];

        if ($this->operator()->hasPermission(Permission::GROUP_STAFF)) {
            $related_listings['note'] = $this->get('Models\Note::class')::filter($load_by_model);
        }

        return $this->viewport('related_listings', $related_listings);
    }

    public function by_name()
    {
        $g = $this->modelClassName()::by_name($this->router()->params('name'));
        $this->router()->hop($this->route_model($g));
    }

    // public function first_contacts()
    // {
    //     $listing = [];
    //     $customers = $this->modelClassName()::filter();
    //     foreach ($customers as $customer) {
    //         if (!$customer->is_legacy()) {
    //             $info = $customer->first_contact_info();
    //             $info['name'] = "$customer";
    //             unset($info['model']);
    //             dd($this->modelClassName());
    //             // dd($this->get())
    //             $new = new $this->modelClassName();
    //             $new->import($info);
    //             $listing[$customer->getId()] = $new;
    //         }
    //     }
    //
    //     $this->viewport('listing_title', 'MODEL_customer_LISTING_first_contacts');
    //     $this->viewport_listing($this->modelClassName(), $listing, $this->find_template($this->get('\Smarty'), __FUNCTION__));
    //
    //     return 'customer/dashboard';
    // }

    public function destroy()
    {
        if ($this->load_model->is_alias()) {
            $original = $this->modelClassName()::one($this->load_model->get('alias_of'));
            $this->routeBack($original);
            parent::destroy();
        }
    }

    public function export()
    {
        $customers = $this->modelClassName()::filter();
        return $this->collection_to_csv($customers, 'koral_'.$this->modelPrefix(Dato::today()));
    }

    public function routeBack($goto = null, $route_params = []): string
    {
        if (!is_null($goto)) {
            $this->route_back = $this->routeFactory($goto);
        } elseif (is_null($this->route_back)) {
            $this->route_back = $this->router()->hyp($this->modelPrefix());
        }

        return $this->route_back;
    }
}
