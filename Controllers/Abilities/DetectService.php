<?php

namespace HexMakina\koral\Controllers\Abilities;

use HexMakina\kadro\Auth\Permission;
use HexMakina\BlackBox\RouterInterface;
use HexMakina\BlackBox\ORM\ModelInterface;

trait DetectService
{
    public $detected_service = null;

    abstract public function router(): RouterInterface;
    abstract public function viewport($key = null, $value = null, $coercion = false);
    abstract public function listing($model = null, $filters = [], $options = []);
    abstract public function formModel(): ModelInterface;

    public function dashboard()
    {
        return $this->dashboard_listing();
    }

    protected function dashboard_listing($model = null, $template = null, $filters = [], $options = [])
    {
        $model = $model ?? $this->load_model ?? $this->formModel();

        $this->viewport('service', $this->detected_service());
        $this->listing($model, $filters, $options);
        $this->viewport('listing_template', $template, true);
        return 'listing';
    }

    public function service_search_match(): array
    {
        $ret = [];
        if (!empty($res = $this->formModel()->get('service_id'))) {
            $ret['id'] = $res;
        } elseif (!empty($res = $this->router()->params('service_abbrev'))) {
            $ret['abbrev'] = $res;
        } elseif (!empty($res = $this->router()->params('service_id'))) {
            $ret['id'] = $res;
        }

      // elseif(!empty($res=$this->router()->params('session_id')))
      //   $ret['id']=Service::one(Session::one($this->router()->params('session_id'))->get('service_id'))->getId();

        elseif (preg_match('/^service_([A-Z]+)$/', $this->router()->name(), $res) === 1) {
            $ret['abbrev'] = $res[1];
        } elseif (!empty($_REQUEST['service_id'])) {
            $ret['id'] = $_REQUEST['service_id'];
        } elseif (isset($this->load_model) && !empty($res = $this->load_model->get('service_id'))) {
            $ret['id'] = $res;
        }

        return $ret;
    }

    public function detected_service($setter = null)
    {
        if ($setter !== null) {
            $this->detected_service = $setter;
        } elseif (is_null($this->detected_service)) {
            $this->detected_service = $this->get('Models\Service::class')::exists($this->service_search_match());
        }

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
        if (!is_null($this->detected_service())) {
            $this->viewport('service', $this->detected_service());
        }

        $this->viewport('dashboard_header', 'service/header.html');
    }

    public function service_permission($model = null)
    {
        $model = $model ?? $this->detected_service();
        $permissions_by_abbrev = $this->get('Models\Service::class')::permissions_by_abbrev();

        if (is_null($model) || !isset($permissions_by_abbrev[$model->get('abbrev')])) {
            return Permission::GROUP_STAFF;
        }

        return $permissions_by_abbrev[$model->get('abbrev')];
    }

    public function DetectServiceTraitor_before_edit()
    {
        if (!is_null($this->detected_service())) {
            $this->authorize($this->service_permission());
            $this->viewport('service', $this->detected_service());
            $this->formModel()->set('service_id', $this->detected_service()->getId()); // TODO replace by name if single_foreign_key to session table
            $this->formModel()->set('service_abbrev', $this->detected_service()->get('abbrev'));
        }
    }
}
