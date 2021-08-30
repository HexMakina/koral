<?php

namespace HexMakina\koral\Controllers\Abilities;

use HexMakina\LogLaddy\LoggerInterface;
use HexMakina\Hopper\RouterInterface;
use HexMakina\kadro\Auth\OperatorInterface;
use HexMakina\koral\Models\{Session,Worker};

trait DetectSession
{
  //based on multiple cascading sources (from post-fresh to db-stored)
    private $detected_session = null;

    abstract public function router(): RouterInterface;
    abstract public function logger(): LoggerInterface;
    abstract public function operator(): OperatorInterface;

    public function session_search_match(): array
    {
        $ret = [];
        if (!empty($res = $this->router()->params('session_id'))) {
            $ret = ['id' => $res];
        } elseif (isset($this->formModel()) && !empty($res = $this->formModel()->get('session_id'))) {
            $ret = ['id' => $res];
        } elseif (isset($this->load_model) && !empty($res = $this->load_model->get('session_id'))) {
            $ret = ['id' => $res];
        }
        return $ret;
    }

    public function detected_session($setter = null)
    {
        if (!is_null($setter)) {
            $this->detected_session = $setter;
        } elseif (is_null($this->detected_session)) {
            $this->detected_session = Session::exists($this->session_search_match());
        }

        return $this->detected_session;
    }

    public function session_track($service, $occured_on)
    {
        $tracked = Session::filter(['service' => $service, 'date_stop' => $occured_on], ['limit' => [0,1]]);
        $tracked = current($tracked);

        return $tracked === false ? null : $tracked;
    }


    public function session_get_or_create($service_id, $occured_on, $label = null): Session
    {
      // fetch the corresponding session
        $session_data = ['service_id' => $service_id, 'occured_on' => $occured_on];
        $sessions = Session::any($session_data);

        if (empty($sessions)) {
            $session_data['label'] = $label;

            $session = new Session();
            $session->import($session_data);

            $session = $this->persist_model($session);

            if (!is_null($session)) {
                $current_worker = Worker::one(['operator_id' => $this->operator()->operator_id()]);
                Worker::set_many([$current_worker], $session);
                // $session->set_many([$current_worker], Worker::otm());
            }
        } else {
            $session = array_pop($sessions);
        }

        return $session;
    }

  // detects (or tracks) session_id, the sets the session_id and occured_on on form_model

  // TODO detect if FK is required, then automate get_or_create()
    public function DetectSessionTraitor_before_save()
    {
        $detected = $this->detected_session();

        if (is_null($detected) && is_subclass_of($this->formModel(), '\App\Models\Interfaces\SessionEventInterface')) {
            $detected = $this->session_track($this->formModel()->event_service(), $this->formModel()->event_value());

            if ($detected->event_value() != $this->formModel()->event_value()) {
              // TODO trait must return array of messages indexed by level
                $this->logger()->info($this->l('MODEL_EventInterface_FOUND_WITHOUT_EXACT_OCCURENCE', [$this->l('MODEL_session_INSTANCE')]));
            }
        }

        if (!is_null($detected)) {
            $foreign_tables = get_class($this->formModel())::table()->foreign_keys_by_table() ?? [];
            $foreign_table_name = Session::table()->name();

            if (isset($foreign_tables[$foreign_table_name]) && count($column = $foreign_tables[$foreign_table_name]) === 1) {
                $column = current($column);
                if (!$column->is_nullable()) {
                    $this->formModel()->set($column->name(), $detected->get_id()); // TODO: replace by name if single_foreign_key to session table
                    $this->formModel()->set('session_occured_on', $detected->event_value());
                }
            }
        }
    }
}
