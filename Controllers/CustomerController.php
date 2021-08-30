<?php

namespace HexMakina\koral\Controllers;

use \HexMakina\Tempus\Dato; // Dato dependency only for export feature, move to ReportController ?

class CustomerController extends \HexMakina\kadro\Controllers\ORMController
{
    use \HexMakina\kadro\Controllers\Abilities\Traceable;
    use Abilities\DetectCustomer;

    public function prepare()
    {
        parent::prepare();
        $this->get('StateAgent')->resetFilters('service_abbrev');
    }

    public function dashboard()
    {
        return $this->dashboard_listing();
    }

    public function edit_alias()
    {
        $customer = $this->modelClassName()::one($this->router()->params($this->modelPrefix('id')));
        $alias = is_null($this->router()->params('alias_id')) ? $this->modelClassName()::make_alias_of($customer) :  $this->modelClassName()::one($this->router()->params('alias_id'));

        $this->viewport('form_model', $alias);
        $this->viewport($this->modelPrefix('original'), $customer);
        $this->route_back($customer);
    }

    public function edit()
    {
        parent::edit();

        if ($this->router()->submits() && !empty($this->router()->submitted()['alias_of'])) {
            $this->router()->hop($this->modelPrefix('new_alias'), [$this->modelPrefix('id') => $this->router()->submitted()['alias_of']]);
        }

        if ($this->formModel()->is_alias()) {
            $this->router()->hop($this->modelPrefix('edit_alias'), ['alias_id' => $this->formModel()->get_id(), $this->modelPrefix('id') => $this->formModel()->get('alias_of')]);
        }

        if (!$this->formModel()->is_new()) {
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

        if (is_null($customer) || $customer->is_new()) {
            return [];
        }

        $related_listings = [];

        $load_by_model = [$this->modelPrefix() => $customer];

        if ($this->operator()->has_permission('group_social')) {
            $related_listings['note'] = $this->get('NoteClass')::filter($load_by_model);
        }

        return $this->viewport('related_listings', $related_listings);
;
    }

    public function by_name()
    {
        $g = $this->modelClassName()::by_name($this->router()->params('name'));
        $this->router()->hop($this->route_model($g));
    }

    public function first_contacts()
    {
        $listing = [];
        $customers = $this->modelClassName()::filter();
        foreach ($customers as $customer) {
            if (!$customer->is_legacy()) {
                $info = $customer->first_contact_info();
                $info['name'] = "$customer";
                unset($info['model']);

                $new = new $this->modelClassName();
                $new->import($info);
                $listing[$customer->get_id()] = $new;
            }
        }

        $this->viewport('listing_title', 'MODEL_customer_LISTING_first_contacts');
        $this->viewport_listing($this->modelClassName(), $listing, $this->find_template($this->get('template_engine'), __FUNCTION__));

        return 'customer/dashboard';
    }

    public function destroy()
    {
        if ($this->load_model->is_alias()) {
            $original = $this->modelClassName()::one($this->load_model->get('alias_of'));
            $this->route_back($original);
            parent::destroy();
        }
    }

    public function export()
    {
        $customers = $this->className()::filter();
        return $this->collection_to_csv($customers, 'koral_customers_' . Dato::today());
    }

    public function route_back($goto = null, $route_params = []): string
    {
        if (!is_null($goto)) {
            $this->route_back = $this->route_factory($goto);
        } elseif (is_null($this->route_back)) {
            $this->route_back = $this->router()->prehop('customer');
        }

        return $this->route_back;
    }
}
