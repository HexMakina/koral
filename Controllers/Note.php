<?php

namespace HexMakina\koral\Controllers;

class Note extends \HexMakina\kadro\Controllers\ORM
{
    use \HexMakina\koral\Controllers\Abilities\DetectService;
    use \HexMakina\koral\Controllers\Abilities\DetectSession;
    use \HexMakina\koral\Controllers\Abilities\DetectCustomers;
    use \HexMakina\koral\Controllers\Abilities\DetectItems;
    use \HexMakina\kadro\Controllers\Abilities\Traceable;

    public function before_edit()
    {
        if (!empty($hold_id = $this->get('HexMakina\BlackBox\StateAgentInterface')->filters('item_hold_id')) && $this->formModel()->isNew() && empty($this->formModel()->item_ids())) {
            $this->formModel()->set('item_ids', [$hold_id]);
        }
    }

    public function edit()
    {
        parent::edit();
        if (!is_null($detected_session = $this->detected_session())) {
            $this->formModel()->set('session_id', $detected_session->getId());
            $this->formModel()->set('occured_on', $detected_session->get('occured_on'));
            $this->formModel()->set('service_id', $detected_session->get('service_id'));
            $this->formModel()->set('service_abbrev', $detected_session->get('service_abbrev'));
        } elseif (!is_null($this->detected_service())) {
            $this->formModel()->set('service_id', $this->detected_service()->getId());
            $this->formModel()->set('service_abbrev', $this->detected_service()->get('service_abbrev'));

            $this->formModel()->set('session_id', null);
            $this->formModel()->set('belongs_to_session', 0);
        }

        if ($this->formModel()->isNew() || is_null($this->formModel()->get('belongs_to_session'))) {
            $this->formModel()->set('belongs_to_session', 1);
        }

        $this->formModel()->set('todo', $this->formModel()->get('todo') ?? 0);


        if (empty($this->formModel()->get('occured_on'))) {
            $this->formModel()->set('occured_on', $this->get('Models\Note::class')::today());
        }
    }


    public function by_service()
    {

        $this->viewport('page_header_title', $this->detected_service()->get('abbrev') . ' | ' . $this->l('MODEL_note_INSTANCES'));

        $filters = [];
        $filters['note_type'] = $this->router()->params('type');
        $filters['service'] = $this->detected_service();

        return $this->dashboard_listing($this->get('Models\Note::new'), 'note/listing.html', $filters);
    }

    public function conclude()
    {
        parent::conclude();

        if (!is_null($this->detected_service())) {
            $this->routeBack('service_abbrev', ['service_abbrev' => $this->detected_service()->get('abbrev')]);
        }

        if (!is_null($detected_session = $this->detected_session())) {
            $this->routeBack($detected_session);
        }
    }

    public function before_save(): array
    {
        if (empty($this->formModel()->get('belongs_to_session'))) { // service note?
            $this->formModel()->set('session_id', null);
        } else {
            $session = $this->session_get_or_create($this->formModel()->get('service_id'), $this->formModel()->get('occured_on'));
            if (!is_null($session)) {
                $this->formModel()->set('occured_on', $session->get('occured_on'));
                $this->formModel()->set('session_id', $session->getId());
                $this->formModel()->set('service_id', $session->get('service_id'));
            }
        }

        return parent::before_save();
    }

    public function after_destroy()
    {
        if (!is_null($session = $this->detected_session())) {
            $this->routeBack($session);
        } elseif (!is_null($this->detected_service())) {
            $this->routeBack($this->detected_service());
        }

        parent::after_destroy();
    }
}
