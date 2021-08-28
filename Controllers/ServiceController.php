<?php

namespace HexMakina\koral\Controllers;

use HexMakina\kadro\Auth\AccessRefusedException;
use HexMakina\koral\Models\{Service,Session};

class ServiceController extends \HexMakina\kadro\Controllers\ORMController
{
    use Abilities\DetectService;

    protected $time_window = null;

    public function authorize($permission = null): bool
    {
        if (is_null($this->detected_service())) {
            if ($this->router()->name() !== 'services_journalier' && $this->router()->name() !== 'services_planner') {
                $this->logger()->warning($this->l('KADRO_CRUDITES_ERR_INSTANCE_NOT_FOUND', [$this->l('MODEL_service_INSTANCE')]));
                $this->router()->hop();
            }
        } else {
            return $this->service_authorize();
        }

        return false;
    }

    public function prepare()
    {
        parent::prepare();

        $this->time_window = [
        'date_start' => $this->box('StateAgent')->filters('date_start'),
        'date_stop' => $this->box('StateAgent')->filters('date_stop')
        ];

        if (!is_null($this->detected_service)) {
            $this->box('StateAgent')->filters('service_abbrev', $this->detected_service()->get('abbrev'));
            $this->viewport('service', $this->detected_service());
        }
    }

    public function dashboard()
    {
        if ($this->operator()->has_permission('group_medical') && !$this->operator()->has_permission('group_social')) {
            $this->router()->hop('fichemedicale'); // move to liste of patients
        }

        if (is_null($this->router()->params('service_abbrev'))) {
            $this->router()->hop('services_journalier'); // move to general journalier
        }

        if ($this->router()->params('service_abbrev') == Service::PI) {
            $this->planner();
            return 'service/planner';
        }

        $this->journalier();
        return 'service/journalier';
    }

    public function journalier()
    {
        $filters = $this->time_window;

        $service = Service::exists('abbrev', $this->router()->params('service_abbrev'));
        if (is_null($service)) {
            if ($this->operator()->has_permission('group_medical') && !$this->operator()->has_permission('group_social')) {
                $service = Service::one(['abbrev' => Service::PM]);
            }
        }
        $this->service_authorize($service);

        $filters['service'] = $service;
        $this->viewport('service', $service, true);

        if ($this->operator()->has_permission('group_medical')) {
            $filters['medical'] = true;
        }

        $this->viewport('journalier', Service::journalier($filters));
    }

    public function planner()
    {
        $filters = [
        'date_start' => $this->box('StateAgent')->filters('date_start'),
        'date_stop' => $this->box('StateAgent')->filters('date_stop')
        ];

        $service = Service::exists('abbrev', $this->router()->params('service_abbrev'));
        if (is_null($service)) {
            if ($this->operator()->has_permission('group_medical') && !$this->operator()->has_permission('group_social')) {
                $service = Service::one(['abbrev' => Service::PM]);
            }
        }

        $this->service_authorize($service);

        $filters['service'] = $service;

        $this->viewport('service', $filters['service'], true);
        $this->viewport('planner', Service::planner($filters));


      // TODO default_date is today if date_start < today < date_stop
      // TODO if $today > date_stop || ^today < date_start -> take stop||start as default
        $this->viewport('date_start', $this->box('StateAgent')->filters('date_start'));
        $this->viewport('date_stop', $this->box('StateAgent')->filters('date_stop'));
    }

  // public function sessions()
  // {
  //
  //   return $this->dashboard_listing(new Session(), 'session/listing.html', ['service' => $this->detected_service()]);
  // }
  //
  // // note de service
  // public function notes()
  // {
  //   $filters=[];
  //   $filters['service']=$this->detected_service();
  //   $filters['note_type']=$this->router()->params('type');
  //
  //   return $this->dashboard_listing(new Note(), 'note/listing.html', $filters);
  // }
  //
  //
}
