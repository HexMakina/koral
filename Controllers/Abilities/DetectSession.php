<?php

namespace HexMakina\koral\Controllers\Abilities;

use Psr\Log\LoggerInterface;
use HexMakina\BlackBox\RouterInterface;
use HexMakina\BlackBox\Auth\OperatorInterface;
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
        } elseif (!empty($res = $this->formModel()->get('session_id'))) {
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
                $current_worker = Worker::one(['operator_id' => $this->operator()->getId()]);
                Worker::setMany([$current_worker], $session);
            }
        } else {
            $session = array_pop($sessions);
        }

        return $session;
    }

  // detects (or tracks) session_id, the sets the session_id and occured_on on form_model

  // TODO detect if FK is required, then automate get_or_create() when tracking fails
    public function DetectSessionTraitor_before_save()
    {
        // do we have a session_id somewhere ?
        $detected = $this->detected_session();

        if (is_null($detected) && self::isSessionEvent($this->formModel())) {
            $detected = $this->session_track($this->formModel()->event_service(), $this->formModel()->event_value());

            if ($detected->event_value() != $this->formModel()->event_value()) {
              // TODO trait must return array of messages indexed by level
                $this->logger()->warning('MODEL_EventInterface_FOUND_WITHOUT_EXACT_OCCURENCE', ['MODEL_session_INSTANCE']);
            }
        }

        if (!is_null($detected)) {
            self::makeSessionEvent($this->formModel(), $detected);
        }
    }
    // checks if a model is a Session Event
    public static function isSessionEvent($m): bool
    {
      return is_subclass_of($m, '\HexMakina\koral\Models\Interfaces\SessionEventInterface');
    }

    // this is way to close to the database, need abstraction
    public static function makeSessionEvent($model, $session)
    {
      $foreign_tables = get_class($model)::table()->foreignKeysByTable() ?? [];
      $foreign_table_name = get_class($session)::table()->name();

      if (isset($foreign_tables[$foreign_table_name]) && count($column = $foreign_tables[$foreign_table_name]) === 1) {
          $column = current($column);
          if (!$column->isNullable()) {
              $model->set($column->name(), $session->getId());
              $model->set('session_occured_on', $session->event_value());
          }
      }
    }
}
