<?php

namespace HexMakina\koral\Controllers;

class SessionController extends \HexMakina\kadro\Controllers\ORMController
{
    use \HexMakina\kadro\Controllers\Abilities\Traceable;
    use \HexMakina\koral\Controllers\Abilities\DetectService;
    use \HexMakina\koral\Controllers\Abilities\DetectItems;

    public function edit()
    {
        parent::edit();

        if (empty($this->formModel()->get('occured_on'))) {
            $this->formModel()->set('occured_on', $this->get('SessionClass')::today());
        }

        if (!is_null($this->detected_service())) {
            $this->formModel()->set('service_id', $this->detected_service()->get_id());
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
        return $this->dashboard_listing($this->get('SessionModel'), 'session/listing.html', ['service' => $this->detected_service()]);
    }


    public function before_save(): array
    {
        $this->service_authorize();
        return [];
    }

    public function after_save()
    {
        if ($this->formModel()->worker_changes($this->load_model)) {
            $this->logger()->nice($this->l('MODEL_LINKED_ALTERATIONS', [$this->l('MODEL_worker_INSTANCES')]));
        } else {
            $this->logger()->info($this->l('MODEL_LINKED_NO_ALTERATIONS', [$this->l('MODEL_worker_INSTANCES')]));
        }

        $worker_ids = [];
        if (property_exists($this->formModel(), 'worker_ids') && is_array($this->formModel()->worker_ids)) {
            $worker_ids = $this->formModel()->worker_ids;
        }
        $this->get('WorkerClass')::set_many_by_ids($worker_ids, $this->formModel());

        parent::after_save();
    }

  // build for ajax call by fullcalendar drag and drop
  // TODO this should be FullCalendarTrait
    public function change_occurence()
    {
        try {
            $new_occured_on = $this->get('SessionClass')::date($this->router()->submitted('new_occured_on'));
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
