<?php

namespace HexMakina\koral\Controllers;

use HexMakina\Tempus\Dato; // Dato dependency only for export feature, move to ReportController ?
use HexMakina\koral\Models\{Customer,Note,Item};
use HexMakina\kadro\Controllers\Abilities\Traceable;

class CustomerController extends \HexMakina\kadro\Controllers\ORMController
{
    use Abilities\DetectCustomer;
    use Traceable;

    public function prepare()
    {
        parent::prepare();
        $this->box('StateAgent')->resetFilters('service_abbrev');
    }

    public function dashboard()
    {
        return $this->dashboard_listing();
    }

    public function edit_alias()
    {
        $customer = Customer::one($this->router()->params('customer_id'));
        $alias = is_null($this->router()->params('alias_id')) ? Customer::make_alias_of($customer) :  Customer::one($this->router()->params('alias_id'));

        $this->viewport('form_model', $alias);
        $this->viewport('customer_original', $customer);
        $this->route_back($customer);
    }

    public function edit()
    {
        parent::edit();

        if ($this->router()->submits() && !empty($this->router()->submitted()['alias_of'])) {
            $this->router()->hop('customer_new_alias', ['customer_id' => $this->router()->submitted()['alias_of']]);
        }

        if ($this->form_model->is_alias()) {
            $this->router()->hop('customer_edit_alias', ['alias_id' => $this->form_model->get_id(), 'customer_id' => $this->form_model->get('alias_of')]);
        }

        if (!$this->form_model->is_new()) {
            $this->viewport('customer_aliases', $this->form_model->listing_aliases());
        }

        if (empty($this->form_model->get('original_name'))) {
            if (!is_null($this->form_model->get('alias_of')) && !is_null($original_customer = Customer::exists($this->form_model->get('alias_of')))) {
                $this->form_model->set('original_name', $original_customer->get('name'));
            } else {
                $this->form_model->set('original_name', '');
            }
        }

        $this->related_listings();
    }

    private function related_listings($customer = null)
    {
        $customer = $customer ?? $this->load_model;

        if (is_null($customer) || $customer->is_new()) {
            return [];
        }

        $related_listings = [];

        $load_by_customer = ['customer' => $customer];

        if ($this->operator()->has_permission('group_social')) {
            $related_listings['note'] = Note::filter($load_by_customer);
        }

        $this->viewport('related_listings', $related_listings);
        return $related_listings;
    }

    public function by_name()
    {
        $g = Customer::by_name($this->router()->params('name'));
        $this->router()->hop($this->route_model($g));
    }

    public function first_contacts()
    {
        $listing = [];
        $customers = Customer::filter();
        foreach ($customers as $customer) {
            if (!$customer->is_legacy()) {
                $info = $customer->first_contact_info();
                $info['name'] = "$customer";
                unset($info['model']);

                $new = new Customer();
                $new->import($info);
                $listing[$customer->get_id()] = $new;
            }
        }

        $this->viewport('listing_title', 'MODEL_customer_LISTING_first_contacts');
        $this->viewport_listing($this->class_name(), $listing, $this->find_template($this->box('template_engine'), __FUNCTION__));

        return 'customer/dashboard';
    }

    public function destroy()
    {
        if ($this->load_model->is_alias()) {
            $original = Customer::one($this->load_model->get('alias_of'));
            $this->route_back($original);
            parent::destroy();
        }
    }

    public function export()
    {
        $customers = Customer::listing_with_fichedonnesociale();
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
