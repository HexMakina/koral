<?php

namespace HexMakina\koral\Controllers;

use HexMakina\koral\Models\{Service,Session,Item,Worker};
use HexMakina\kadro\Controllers\Abilities\Traceable;

class SessionController extends \HexMakina\kadro\Controllers\ORMController
{
    use Abilities\DetectService;
    use Abilities\DetectItems;
    use Traceable;

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
      // $this->viewport('session_notes', Note::filter(['session' => $this->form_model]));
        $this->related_listings();
    }

    private function related_listings($model = null)
    {
        $model = $model ?? $this->load_model;

        if (is_null($model) || $model->is_new()) {
            return [];
        }

        $load_by_session = ['session' => $model];
        $related_listings = [];
        $related_listings['note'] = Note::filter($load_by_session);
        if (!is_null($this->detected_service())) {
            ;
        }

        $this->viewport('related_listings', $related_listings);
        return $related_listings;
    }

    public function by_service()
    {
        $this->viewport('page_header_title', $this->detected_service()->get('abbrev') . ' | ' . L('MODEL_session_INSTANCES'));
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
            $this->logger()->nice(L('MODEL_LINKED_ALTERATIONS', [L('MODEL_worker_INSTANCES')]));
        } else {
            $this->logger()->info(L('MODEL_LINKED_NO_ALTERATIONS', [L('MODEL_worker_INSTANCES')]));
        }

        $worker_ids = [];
        if (property_exists($this->form_model, 'worker_ids') && is_array($this->form_model->worker_ids)) {
            $worker_ids = $this->form_model->worker_ids;
        }
        Worker::set_many_by_ids($worker_ids, $this->form_model);

        parent::after_save();
    }

  // build for ajax call by fullcalendar drag and drop
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
