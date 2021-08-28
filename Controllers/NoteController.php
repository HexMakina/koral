<?php

namespace HexMakina\koral\Controllers;

use HexMakina\koral\Models\{Service,Session,Note};
use HexMakina\kadro\Controllers\Abilities\Traceable;

class NoteController extends \HexMakina\kadro\Controllers\ORMController
{
    use Abilities\DetectService;
    use Abilities\DetectSession;
    use Abilities\DetectCustomers;
    use Abilities\DetectItems;
    use Traceable;

    public function before_edit()
    {
        if (!empty($hold_id = $this->box('StateAgent')->filters('item_hold_id')) && $this->form_model->is_new() && empty($this->form_model->item_ids())) {
            $this->form_model->set('item_ids', [$hold_id]);
        }
    }

    public function edit()
    {
        parent::edit();
        if (!is_null($detected_session = $this->detected_session())) {
            $this->form_model->session_id = $detected_session->get_id();
            $this->form_model->set('occured_on', $detected_session->get('occured_on'));
            $this->form_model->set('service_id', $detected_session->get('service_id'));
            $this->form_model->set('service_abbrev', $detected_session->get('service_abbrev'));
        } elseif (!is_null($this->detected_service())) {
            $this->form_model->set('service_id', $this->detected_service()->get_id());
            $this->form_model->set('service_abbrev', $this->detected_service()->get('service_abbrev'));

            $this->form_model->set('session_id', null);
            $this->form_model->set('belongs_to_session', 0);
        }

        if ($this->form_model->is_new() || is_null($this->form_model->get('belongs_to_session'))) {
            $this->form_model->set('belongs_to_session', 1);
        }

        $this->form_model->set('todo', $this->form_model->get('todo') ?? 0);


        if (empty($this->form_model->get('occured_on'))) {
            $this->form_model->set('occured_on', Note::today());
        }
    }


    public function by_service()
    {

        $this->viewport('page_header_title', $this->detected_service()->get('abbrev') . ' | ' . $this->l('MODEL_note_INSTANCES'));

        $filters = [];
        $filters['note_type'] = $this->router()->params('type');
        $filters['service'] = $this->detected_service();
        return $this->dashboard_listing(new Note(), 'note/listing.html', $filters);
    }

    public function conclude()
    {
        parent::conclude();

        if (!is_null($this->detected_service())) {
            $this->route_back('service_abbrev', ['service_abbrev' => $this->detected_service()->get('abbrev')]);
        }

        if (!is_null($detected_session = $this->detected_session())) {
            $this->route_back($detected_session);
        }
    }

    public function before_save(): array
    {
        if (empty($this->form_model->get('belongs_to_session'))) { // service note?
            $this->form_model->set('session_id', null);
        } else {
            $session = $this->session_get_or_create($this->form_model->get('service_id'), $this->form_model->get('occured_on'));
            if (!is_null($session)) {
                $this->form_model->set('occured_on', $session->get('occured_on'));
                $this->form_model->set('session_id', $session->get_id());
                $this->form_model->set('service_id', $session->get('service_id'));
            }
        }

        return parent::before_save();
    }

    public function after_destroy()
    {
        if (!is_null($session = $this->detected_session())) {
            $this->route_back($session);
        } elseif (!is_null($this->detected_service())) {
            $this->route_back($this->detected_service());
        }

        parent::after_destroy();
    }
}
