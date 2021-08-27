<?php

namespace HexMakina\koral\Controllers\Abilities;

use \HexMakina\koral\Models\Customer;

// TODO Rewrite liek DetectCustomer

// Point is to set the customer_ids in different conditions
trait DetectCustomers
{
  private $detected_customers = null;

  public function DetectCustomersTraitor_before_edit()
  {

    $this->form_model->set('customer_names', implode(PHP_EOL, $this->detected_customers()));
  }

  public function DetectCustomersTraitor_before_save()
  {
    $this->form_model->set('customer_ids', array_keys($this->detected_customers()));
  }

  public function customer_search_names()  : array
  {
    $customer_names=[];

    if(isset($this->form_model) && (!empty($customer_names=$this->form_model->get('customer_names')) || $this->router()->submits()))
      ;
    elseif(isset($this->load_model) && !empty($customer_names=$this->load_model->get('customer_names')))
      ;


    if(empty($customer_names))
      return [];
    elseif(is_string($customer_names))
    {
      $separator = $this->router()->submits() ? PHP_EOL : ',';
      return explode($separator,trim($customer_names));
    }

    return $customer_names;
  }

  public function detected_customers($setter=null) : array
  {
    if(!is_null($setter))
      $this->detected_customers=$setter;

    elseif(is_null($this->detected_customers))
    {
      $this->detected_customers=empty($customer_names = $this->customer_search_names()) ? [] : Customer::by_names($customer_names);

      if(!empty($customer=Customer::exists($this->router()->params('customer_id'))))
        $this->detected_customers[]=$customer;
    }

    return $this->detected_customers;
  }

}

?>
