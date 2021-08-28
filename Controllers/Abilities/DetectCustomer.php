<?php

namespace HexMakina\koral\Controllers\Abilities;

use HexMakina\koral\Models\Customer;
use HexMakina\TightORM\Interfaces\ModelInterface;
use HexMakina\Hopper\RouterInterface;

trait DetectCustomer
{
    private $detected_customer = null;

    abstract public function route_model(ModelInterface $model): string;
    abstract public function router(): RouterInterface;
    abstract public function viewport($key = null, $value = null, $coercion = false);
    abstract public function listing();

    public function customer_search_match(): array
    {
        $ret = [];

        if (!empty($res = $this->router()->params('customer_id'))) {
            $ret['id'] = $res;
        } elseif (!empty($res = $this->router()->params('customer_name'))) {
            $ret['name'] = $res;
        } elseif (isset($this->form_model)) {
            if (!empty($res = $this->form_model->get('customer_id'))) {
                $ret['id'] = $res;
            } elseif (!empty($res = $this->form_model->get('customer_name'))) {
                $ret['name'] = $res;
            }
        } elseif (isset($this->load_model)) {
            if (!empty($res = $this->load_model->get('customer_id'))) {
                $ret['id'] = $res;
            } elseif (!empty($res = $this->load_model->get('customer_name'))) {
                $ret['name'] = $res;
            }
        }

        return $ret;
    }

    public function customer_route_back($goto = null, $route_params = []): string
    {
        if (!is_null($this->detected_customer())) {
            return $this->route_model($this->detected_customer());
        }
        return parent::route_back($goto, $route_params);
    }

    public function customer_dashboard()
    {
        parent::dashboard();
        $this->viewport('customer', $this->detected_customer());
        return 'customer/dashboard';
    }

    public function detected_customer($setter = null)
    {
        if (!is_null($setter)) {
            $this->detected_customer = $setter;
        } elseif (is_null($this->detected_customer)) {
            $this->detected_customer = Customer::exists($this->customer_search_match());
        }

        return $this->detected_customer;
    }

    public function DetectCustomerTraitor_before_edit()
    {
        if ($this->router()->requests() && !is_null($this->detected_customer())) { // create a note with a customer
            $this->form_model->set('customer_name', $this->detected_customer()->name());
            $this->form_model->set('customer_id', $this->detected_customer()->get_id());
        }
    }

    public function DetectCustomerTraitor_before_save()
    {
        if (empty($this->form_model->get('customer_id')) && !is_null($this->detected_customer())) {
            $this->form_model->set('customer_id', $this->detected_customer()->get_id());
        }
    }

    public function dashboard()
    {
        return $this->dashboard_listing();
    }


    public function DetectCustomerTraitor_prepare()
    {
        if (!is_null($this->detected_customer())) {
            $this->viewport('related_customer', $this->detected_customer());
        }
        $this->viewport('dashboard_header', 'customer/header.html');
    }


    protected function dashboard_listing($model = null, $template = null, $filters = [], $options = [])
    {
        $model = $model ?? $this->load_model ?? $this->form_model;

        $this->viewport('related_customer', $this->detected_customer());
        $this->listing($model, $filters, $options);
        $this->viewport('listing_template', $template, true);
        return 'listing';
    }
}
