<?php

namespace HexMakina\koral\Controllers;

use HexMakina\koral\Models\{Service,Session,Note,Item,Worker};

class SessionController extends \HexMakina\kadro\Controllers\ORMController
{
    use Abilities\DetectService;
    use Abilities\DetectItems;
    use \HexMakina\kadro\Controllers\Abilities\Traceable;

    public function edit()
    {
        parent::edit();

        if (empty($this->form_model->get('occured_on'))) {
            $this->form_model->set('occured_on', Session::today());
        }

        if (!is_null($this->detected_service())) {
            $this->form_model->set('service_id', $this->detected_service()->get_id());
            $this->route_back('service_abbrev', ['service_abbrev' => $this->detected_service()->get('abbrev')]);
        }
        $this->related_listings();
    }

    protected function related_listings($model = null)
    {
        $model = $model ?? $this->load_model;

        if (is_null($model) || $model->is_new()) {
            return [];
        }

        $related_listings = [];

        $related_listings['note'] = $this->get('NoteClass')::filter(['session' => $model]);

        return $this->viewport('related_listings', $related_listings);
    }

    public function by_service()
    {
        $this->viewport('page_header_title', $this->detected_service()->get('abbrev') . ' | ' . $this->l('MODEL_session_INSTANCES'));
        return $this->dashboard_listing(new Session(), 'session/listing.html', ['service' => $this->detected_service()]);
    }


    public function before_save(): array
    {
        $this->service_authorize();
        return [];
    }

    public function after_save()
    {
        if ($this->form_model->worker_changes($this->load_model)) {
            $this->logger()->nice($this->l('MODEL_LINKED_ALTERATIONS', [$this->l('MODEL_worker_INSTANCES')]));
        } else {
            $this->logger()->info($this->l('MODEL_LINKED_NO_ALTERATIONS', [$this->l('MODEL_worker_INSTANCES')]));
        }

        $worker_ids = [];
        if (property_exists($this->form_model, 'worker_ids') && is_array($this->form_model->worker_ids)) {
            $worker_ids = $this->form_model->worker_ids;
        }
        Worker::set_many_by_ids($worker_ids, $this->form_model);

        parent::after_save();
    }

  // build for ajax call by fullcalendar drag and drop
  // TODO this should be FullCalendarTrait
    public function change_occurence()
    {
        try {
            $new_occured_on = Session::date($this->router()->submitted('new_occured_on'));
            $this->load_model->set('occured_on', $new_occured_on);

            $this->load_model->save($this->operator()->operator_id());

            return json_encode('success');
        } catch (\Exception $e) {
            return json_encode('error');
        }
    }

    public function after_destroy()
    {
        if (!is_null($this->detected_service())) {
            $this->router()->hop('service_abbrev', ['service_abbrev' => $this->detected_service()->get('abbrev')]);
        }

        $this->router()->hop();
    }
}
