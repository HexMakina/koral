<?php

namespace HexMakina\koral\Models;

use \HexMakina\Crudites\Interfaces\SelectInterface;
use \HexMakina\TightORM\TightModel;
use \HexMakina\kadro\Auth\Permission;

class Service extends TightModel
{
  // service with exeception
    const ADM = 'ADM';

    public static $abbrevs = null;

    public function __toString()
    {
        return $this->get('abbrev');
    }

    public function is($abbrev)
    {
        return self::abbrevs($this->get('abbrev'), $abbrev);
    }

    public static function permissions_by_abbrev(): array
    {
        return [self::ADM => Permission::GROUP_ADMIN];
    }

    public static function planner($filters)
    {
        return Session::filter($filters);
    }

    public static function journalier($filters)
    {
        $journalier = [];

        $services = null;
        if (isset($filters['service'])) {
            $services = [$filters['service']];
        } elseif (isset($filters['services'])) {
            $services = $filters['services'];
        } else {
            $services = Service::filter();
        }

      // vd($services);
        foreach ($services as $service) {
            $filters['service'] = $service;

            $service_sessions = Session::filter($filters);

            foreach ($service_sessions as $session) {
                $journalier[$session->event_value()] = $journalier[$session->event_value()] ?? [];
                $journalier[$session->event_value()] [] = $session;
            }

            // if ($service->is(Service::PM)) {
            //     $service_events = [new FichePMAccueil(), new FichePMDonnee()];
            //     if (isset($filters['medical']) && $filters['medical'] === true) {
            //         array_unshift($service_events, new Consultation());
            //         array_unshift($service_events, new FicheMedicale());
            //     }
            //     foreach ($service_events as $service_event) {
            //         foreach (get_class($service_event)::filter($filters) as $event) {
            //             // vd($event);
            //             $journalier[$session->event_value()] = $journalier[$session->event_value()] ?? [];
            //             $journalier[$session->event_value()] [] = $event;
            //         }
            //     }
            // }

            $Query = Note::query_retrieve($filters)->aw_numeric_in('session_id', array_keys($service_sessions));
            $session_notes = empty($service_sessions) ? [] : Note::retrieve($Query);

            foreach ($session_notes as $note) {
                $journalier[$note->event_value()] = $journalier[$note->event_value()] ?? [];
                $journalier[$note->event_value()] [] = $note;
            }

            $Query = Note::query_retrieve($filters)->aw_empty('session_id');
            $service_notes = Note::retrieve($Query);
            foreach ($service_notes as $note) {
                $journalier[$note->event_value()] = $journalier[$note->event_value()] ?? [];
                $journalier[$note->event_value()] [] = $note;
            }
        }

        krsort($journalier);

        return $journalier;
    }

  /*
   * loads (once) the service abbrevs in [id => abbrev]
   *
   * USAGE
   *
   * Service::abbrev()                      Returns []        all abbrevs
   * Service::abbrev($abbrev)               Returns boolean   $abbrev exists
   * Service::abbrev($abbrev, $compare_to)  Returns boolean   $abbrev & $compare_to exist & are equal
   *
   */
    public static function abbrevs($abbrev = null, $compare_to = null)
    {
        if (is_null(self::$abbrevs)) {
            self::$abbrevs = Service::table()->select(['id, abbrev'])->order_by('menu_rank ASC')->ret_par();
        }

        if (is_null($abbrev)) { // returns all
            return self::$abbrevs;
        }

        if (is_null($compare_to)) { //$abbrev exists ?
            return in_array($abbrev, self::$abbrevs);
        }

        if (!in_array($compare_to, self::$abbrevs)) { // compare_to exists ?
            return false;
        }

        $i = array_search($abbrev, self::$abbrevs);
        if ($i === false) {
            return false;
        }

        return self::$abbrevs[$i] === $compare_to;
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $options['order_by'] = $options['order_by'] ?? [['menu_rank', 'ASC']];
        $Query = parent::query_retrieve($filters, $options);
        return $Query;
    }
}
