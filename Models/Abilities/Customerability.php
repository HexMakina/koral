<?php

namespace HexMakina\koral\Models\Abilities;

use \HexMakina\LeMarchand\LeMarchand;

trait Customerability
{
    public $customers = null;
    public $customer_ids = null;

    private $index_customer_fields = [];
    private $index_customer_class = null;


    private function customer_field($field_name)
    {
        if (!isset($this->index_customer_fields[$field_name])) {
            $this->index_customer_fields[$field_name] = LeMarchand::box()->get('CustomerClass')::model_type() . '_' . $field_name;
        }

        return $this->index_customer_fields[$field_name];
    }


    public function customers()
    {
        if (!is_null($this->customers)) {
            return $this->customers;
        }

        if (!$this->is_new()) {
            $customer_class = LeMarchand::box()->get('CustomerClass');


            if (!is_null($this->get($this->customer_field('names')))) {
                // vd($this->get('customer_names'));
                $customer_names = [];

                $customer_names_field = $this->customer_field('names');
                if (strpos($this->get($customer_names_field), PHP_EOL)) {
                    $customer_names = explode(PHP_EOL, $this->get($customer_names_field));
                } elseif (strpos($this->get($customer_names_field), ',')) {
                    $customer_names = explode(',', $this->get($customer_names_field));
                } elseif (!empty($this->get($customer_names_field))) {
                    $customer_names = [trim($this->get($customer_names_field))];
                }

                // vd($customer_names);
                $this->customers = $customer_class::by_names($customer_names);

                // vd($this->customers);
                $this->customer_ids = array_keys($this->customers);
            } else {
                $Query = $customer_class::table()->select(null, 'g');
                $Query->join([$customer_class::otm('t'), 'c_models'], [['c_models', $customer_class::otm('k'), 'g', 'id'], ['c_models', 'model_type', get_class($this)::model_type()], ['c_models', 'model_id', $this->get_id()]], 'INNER');
                $this->customers = $customer_class::retrieve($Query);
                $this->customer_ids = array_keys($this->customers);
            }
        }

        return $this->customers;
    }

    public function customer_alterations($other_model)
    {
        if (!is_null($other_model)) {
            return count($this->customers()) != count($other_model->customers()) || !empty(array_diff($this->customers(), $other_model->customers()));
        }
        return false;
    }

    public function CustomerabilityTraitor_after_save()
    {
        $customer_class = LeMarchand::box()->get('CustomerClass');

        $res = $customer_class::set_many_by_ids($this->get($this->customer_field('ids')), $this);
      // $res = $this->set_many_by_ids($this->get('customer_ids'), Customer::otm());

        if ($res === true) {
            return 'CUSTOMER_CUSTOMERABILITY_CHANGES';
        }

        return $res;
    }
}
