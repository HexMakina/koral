<?php

namespace HexMakina\koral\Controllers\Abilities;

use \HexMakina\Interfaces\RouterInterface;
use \HexMakina\koral\Models\Customer;

// TODO Rewrite liek DetectCustomer

// Point is to set the customer_ids in different conditions
trait DetectCustomers
{
    private $detected_customers = null;
    private $customer_model_type = null;

    abstract public function router(): RouterInterface;

    private function detection_field($field_name)
    {
        return $this->customerModelType() . '_' . $field_name;
    }

    private function customerModelType()
    {
        if (is_null($this->customer_model_type)) {
            $this->customer_model_type = $this->get('Models\Customer::class')::model_type();
        }

        return $this->customer_model_type;
    }

    public function DetectCustomersTraitor_before_edit()
    {

        $this->formModel()->set($this->detection_field('names'), implode(PHP_EOL, $this->detected_customers()));
    }

    public function DetectCustomersTraitor_before_save()
    {
        $this->formModel()->set($this->detection_field('ids'), array_keys($this->detected_customers()));
    }

    public function customer_search_names(): array
    {
        $customer_names = [];

        // holy silken tofu u were motivated to obfuscate
        if (!empty($customer_names = $this->formModel()->get($this->detection_field('names'))) || $this->router()->submits()) {
        } elseif (isset($this->load_model)) {
            $customer_names = $this->load_model->get($this->detection_field('names'));
        }


        if (empty($customer_names)) {
            return [];
        } elseif (is_string($customer_names)) {
            $separator = $this->router()->submits() ? PHP_EOL : ',';
            return explode($separator, trim($customer_names));
        }

        return $customer_names;
    }

    public function detected_customers($setter = null): array
    {
        if (!is_null($setter)) {
            $this->detected_customers = $setter;
        } elseif (is_null($this->detected_customers)) {
            $customer_names = $this->customer_search_names();
            $this->detected_customers = $this->get('Models\Customer::class')::by_names($customer_names);

            if (!empty($customer = $this->get('Models\Customer::class')::exists($this->router()->params($this->detection_field('id'))))) {
                $this->detected_customers[] = $customer;
            }
        }
        return $this->detected_customers;
    }
}
