<?php

namespace HexMakina\koral\Controllers;

class Session extends \HexMakina\kadro\Controllers\ORM
{
    use \HexMakina\kadro\Controllers\Abilities\Traceable;
    use \HexMakina\koral\Controllers\Abilities\DetectService;
    use \HexMakina\koral\Controllers\Abilities\DetectItems;

    public function edit()
    {
        parent::edit();

        if (empty($this->formModel()->get('occured_on'))) {
            $this->formModel()->set('occured_on', $this->get('Models\Session::class')::today());
        }

        if (!is_null($this->detected_service())) {
            $this->formModel()->set('service_id', $this->detected_service()->getId());
            $this->routeBack('service_abbrev', ['service_abbrev' => $this->detected_service()->get('abbrev')]);
        }
        $this->related_listings();
    }

    protected function related_listings($model = null)
    {
        $model = $model ?? $this->load_model;

        if (is_null($model) || $model->isNew()) {
            return [];
        }

        $related_listings = [];

        $related_listings['note'] = $this->get('Models\Note::class')::filter(['session' => $model]);

        return $this->viewport('related_listings', $related_listings);
    }

    public function by_service()
    {
        $this->viewport('page_header_title', $this->detected_service()->get('abbrev') . ' | ' . $this->l('MODEL_session_INSTANCES'));
        return $this->dashboard_listing($this->get('Models\Session::new'), 'session/listing.html', ['service' => $this->detected_service()]);
    }


    public function before_save(): array
    {
        $this->authorize($this->service_permission($this->detected_service()));
        return [];
    }

    public function after_save()
    {
        if ($this->formModel()->worker_changes($this->load_model)) {
            $this->logger()->notice($this->l('MODEL_LINKED_ALTERATIONS', [$this->l('MODEL_worker_INSTANCES')]));
        } else {
            $this->logger()->info($this->l('MODEL_LINKED_NO_ALTERATIONS', [$this->l('MODEL_worker_INSTANCES')]));
        }

        $worker_ids = [];
        if (property_exists($this->formModel(), 'worker_ids') && is_array($this->formModel()->worker_ids)) {
            $worker_ids = $this->formModel()->worker_ids;
        }
        $this->get('Models\Worker::class')::setManyByIds($worker_ids, $this->formModel());

        parent::after_save();
    }

  // build for ajax call by fullcalendar drag and drop
  // TODO this should be FullCalendarTrait
    public function change_occurence()
    {
        try {
            $new_occured_on = $this->get('Models\Session::class')::date($this->router()->submitted('new_occured_on'));
            $this->load_model->set('occured_on', $new_occured_on);

            $this->load_model->save($this->operator()->getId());

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
