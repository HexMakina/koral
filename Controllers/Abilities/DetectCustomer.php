<?php

namespace HexMakina\koral\Controllers\Abilities;

use HexMakina\koral\Models\Customer;
use HexMakina\TightORM\Interfaces\ModelInterface;
use HexMakina\Hopper\RouterInterface;

trait DetectCustomer
{
    private $detected_customer = null;
    private $customer_model_type = null;

    abstract public function route_model(ModelInterface $model): string;
    abstract public function router(): RouterInterface;
    abstract public function viewport($key = null, $value = null, $coercion = false);
    abstract public function listing($model = null, $filters = [], $options = []);

    private function detection_field($field_name)
    {
        return $this->customer_model_type() . '_' . $field_name;
    }

    private function customer_model_type()
    {
        if (is_null($this->customer_model_type)) {
            $this->customer_model_type = $this->get('CustomerClass')::model_type();
        }

        return $this->customer_model_type;
    }

    public function customer_search_match(): array
    {
        $ret = [];

        if (!empty($res = $this->router()->params($this->detection_field('id')))) {
            $ret['id'] = $res;
        } elseif (!empty($res = $this->router()->params($this->detection_field('name')))) {
            $ret['name'] = $res;
        } else {
            if (!empty($res = $this->formModel()->get($this->detection_field('id')))) {
                $ret['id'] = $res;
            } elseif (!empty($res = $this->formModel()->get($this->detection_field('name')))) {
                $ret['name'] = $res;
            }
        }

        //  elseif (isset($this->load_model)) {
        //     if (!empty($res = $this->load_model->get($this->detection_field('id')))) {
        //         $ret['id'] = $res;
        //     } elseif (!empty($res = $this->load_model->get($this->detection_field('name')))) {
        //         $ret['name'] = $res;
        //     }
        // }

        return $ret;
    }

    public function customerRouteBack($goto = null, $route_params = []): string
    {
        if (!is_null($this->detected_customer())) {
            return $this->route_model($this->detected_customer());
        }
        return parent::route_back($goto, $route_params);
    }

    public function customer_dashboard()
    {
        parent::dashboard();
        $this->viewport($this->customer_model_type(), $this->detected_customer());
        return $this->customer_model_type() . '/dashboard';
    }

    public function detected_customer($setter = null)
    {
        if (!is_null($setter)) {
            $this->detected_customer = $setter;
        }
        elseif (is_null($this->detected_customer)) {
            $this->detected_customer = $this->get('CustomerClass')::exists($this->customer_search_match());
        }
        return $this->detected_customer;
    }

    public function DetectCustomerTraitor_before_edit()
    {
        if ($this->router()->requests() && !is_null($this->detected_customer())) { // create a note with a customer
            $this->formModel()->set($this->detection_field('name'), $this->detected_customer()->name());
            $this->formModel()->set($this->detection_field('id'), $this->detected_customer()->get_id());
        }
    }

    public function DetectCustomerTraitor_before_save()
    {
        if (empty($this->formModel()->get($this->detection_field('id'))) && !is_null($this->detected_customer())) {
            $this->formModel()->set($this->detection_field('id'), $this->detected_customer()->get_id());
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
        // dd($this->customer_model_type());
        $this->viewport('dashboard_header', $this->customer_model_type() . '/header.html');
    }


    protected function dashboard_listing($model = null, $template = null, $filters = [], $options = [])
    {
        $model = $model ?? $this->load_model ?? $this->formModel();
        $this->viewport('related_customer', $this->detected_customer());
        $this->listing($model, $filters, $options);
        $this->viewport('listing_template', $template, true);
        return 'listing';
    }
}
