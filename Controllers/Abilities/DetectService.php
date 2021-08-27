<?php

namespace HexMakina\koral\Controllers\Abilities;

use \HexMakina\koral\Models\{Service,Session};

trait DetectService
{
  public $detected_service=null;

  public function dashboard()
  {
    return $this->dashboard_listing();
  }

  protected function dashboard_listing($model=null, $template=null, $filters=[],$options=[])
  {
    $model = $model ?? $this->load_model ?? $this->form_model;

    $this->viewport('service', $this->detected_service());
    $this->listing($model, $filters, $options);
    $this->viewport('listing_template', $template, true);
    return 'listing';
  }

  public function service_search_match()  : array
  {
    $ret=[];
    if(isset($this->form_model) && !empty($res=$this->form_model->get('service_id')))
      $ret['id']=$res;

    elseif(!empty($res=$this->router()->params('service_abbrev')))
      $ret['abbrev']=$res;

    elseif(!empty($res=$this->router()->params('service_id')))
      $ret['id']=$res;

    // elseif(!empty($res=$this->router()->params('session_id')))
    //   $ret['id']=Service::one(Session::one($this->router()->params('session_id'))->get('service_id'))->get_id();

    elseif(preg_match('/^service_([A-Z]+)$/',$this->router()->name(),$res) === 1)
      $ret['abbrev']=$res[1];

    elseif(!empty($_REQUEST['service_id']))
      $ret['id']=$_REQUEST['service_id'];

    elseif(isset($this->load_model) && !empty($res=$this->load_model->get('service_id')))
      $ret['id']=$res;

    return $ret;
  }

  public function detected_service($setter=null)
  {
    if(!is_null($setter))
      $this->detected_service=$setter;

    elseif(is_null($this->detected_service))
      $this->detected_service=Service::exists($this->service_search_match());

    return $this->detected_service;
  }

  public function service_dashboard()
  {
    parent::dashboard();
    $this->viewport('service', $this->detected_service());
    return 'service/dashboard';
  }

  public function DetectServiceTraitor_prepare()
  {
    if(!is_null($this->detected_service()))
    {
      $this->viewport('service', $this->detected_service());

    }

    $this->viewport('dashboard_header', 'service/header.html');
  }

  public function service_authorize($model=null) : bool
  {
    $model = $model ?? $this->detected_service();


    if(is_null($model))
    {
      return parent::authorize('group_social');
    }
    elseif($model->is(Service::ADM))
    {
      return parent::authorize('group_admin');
    }
    elseif($model->is(Service::PM))
    {
      return parent::authorize('group_medical');
    }

    return parent::authorize('group_social');
  }

  public function DetectServiceTraitor_before_edit()
  {
    if(!is_null($this->detected_service()))
    {
      $this->service_authorize();

      $this->viewport('service', $this->detected_service());
      $this->form_model->set('service_id',$this->detected_service()->get_id()); // TODO replace by name if single_foreign_key to session table
      $this->form_model->set('service_abbrev',$this->detected_service()->get('abbrev'));
    }
  }

}

?>
