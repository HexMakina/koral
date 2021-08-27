<?php

namespace HexMakina\koral\Models\Abilities;

use \HexMakina\koral\Models\Customer;

trait Customerability
{
  public $customers = null;
  public $customer_ids = null;

  public function customers()
  {
    if(!is_null($this->customers))
      return $this->customers;


    if(!$this->is_new())
    {

      if(!is_null($this->get('customer_names')))
      {
        // vd($this->get('customer_names'));
        $customer_names = [];
        if(strpos($this->get('customer_names'), PHP_EOL))
          $customer_names = explode(PHP_EOL, $this->get('customer_names'));

        elseif(strpos($this->get('customer_names'), ','))
          $customer_names = explode(',', $this->get('customer_names'));

        elseif(!empty($this->get('customer_names')))
          $customer_names = [trim($this->get('customer_names'))];

        // vd($customer_names);
        $this->customers = Customer::by_names($customer_names);

        // vd($this->customers);
        $this->customer_ids = array_keys($this->customers);
      }
      else
      {

        $Query = Customer::table()->select(null, 'g');
    		$Query->join(['customers_models', 'gm'], [['gm', 'customer_id', 'g', 'id'], ['gm', 'model_type', get_class($this)::model_type()], ['gm', 'model_id', $this->get_id()]], 'INNER');

        $this->customers = Customer::retrieve($Query);
        $this->customer_ids = array_keys($this->customers);
      }
    }


    return $this->customers;
  }

  public function customer_alterations($other_model)
  {
    if(!is_null($other_model))
      return count($this->customers()) != count($other_model->customers()) || !empty(array_diff($this->customers(), $other_model->customers()));
    return false;
  }

  public function CustomerabilityTraitor_after_save()
  {
    $res = Customer::set_many_by_ids($this->get('customer_ids'),$this);
    // $res = $this->set_many_by_ids($this->get('customer_ids'), Customer::otm());

    if($res === true)
      return 'GAST_GASTABILITY_CHANGES';

    return $res;
  }

}

?>
