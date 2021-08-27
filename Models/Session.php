<?php

namespace HexMakina\koral\Models;

use HexMakina\Crudites\Interfaces\SelectInterface;
use HexMakina\Crudites\CruditesException;
use HexMakina\TightORM\TightModel;
use HexMakina\kadro\Auth\Operator;

class Session extends TightModel implements Interfaces\ServiceEventInterface
{
    use Abilities\ServiceEvent;
    use Abilities\Itemability;

    public function __toString()
    {
        return $this->get('label') ?? $this->get('occured_on');
    }

    public function item_types()
    {
        $service_abbrev = $this->get('service_abbrev') ?? Service::abbrevs()[$this->get('service_id')] ?? null;
        $ret = ['session_location'];

        if (isset($service_abbrev)) {
            $ret [] = sprintf('session_%s', $service_abbrev);
        }

        return $ret;
    }


    public function event_label()
    {
        $res = null;
        if (empty($res = $this->get('label'))) {
            if (empty($res = $this->get('worker_names'))) {
                $res = $this->get($this->event_field());
            }
        }

        return sprintf('%s | %s', $this->get('service_abbrev'), $res);
    }

    public function copy()
    {
        $clone = parent::copy();

        $clone->set($this->event_field(), Session::today()); // set current date
        $clone->set('worker_ids', $this->get('worker_ids')); // set current date
        $clone->set('worker_names', $this->get('worker_names')); // set current date

        return $clone;
    }

    public function worker_changes($other_model)
    {
        if (is_null($other_model)) {
            return false;
        }
        // vd($this->worker_ids());
        // dd($other_model->worker_ids());
        return count($this->worker_ids()) != count($other_model->worker_ids()) || !empty(array_diff($this->worker_ids(), $other_model->worker_ids()));
    }

    public function worker_ids()
    {
        $worker_ids = $this->get('worker_ids');

        if (is_null($worker_ids)) {
            return [];
        }

        if (!is_array($worker_ids)) {
            return array_map('trim', explode(',', $this->worker_ids));
        }

        if (array_search('1', $this->worker_ids, true)) {
            return array_keys($this->worker_ids);
        }

        return $worker_ids;
    }

    public function with($setter = null)
    {
        if (!is_null($setter)) {
            if (!is_array($setter)) {
                $setter = [$setter];
            }
            foreach ($setter as $worker) {
                if (is_int($worker)) {
                    $worker_id = $worker;
                } elseif (is_object($worker)) {
                    $worker_id = $worker->get_id();
                }

                try {
                    $record = Table::inspect(Worker::otm()['t'])->produce(['model_type' => 'session', 'model_id' => $this->get_id(), Worker::otm()['k'] => $worker_id]);
                    $record->persist();
                } catch (CruditesException $e) {
                    return [$e->getCode() => $e->getMessage()];
                }
            }
        } else {
            return Worker::filter(['model' => $this]);
        }
    }

    public function immortal(): bool
    {
        if (count($this->with()) > 0) { // cant have users
            return true;
        }

        $linked = Note::any(['session_id' => $this->get_id()]); // can't have notes
        if (count($linked) > 0) {
            return true;
        }

        $linked = Observation::any(['session_id' => $this->get_id()]); // can't have observation (if PI)
        if (count($linked) > 0) {
            return true;
        }

        return false;
    }

    public function kill()
    {
        if ($this->immortal()) {
            return false;
        }
        Worker::set_many([], $this);
        // $this->set_many([], Worker::otm());

        return parent::kill();
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $Query = parent::query_retrieve($filters, $options);

        $Query = self::query_with_item_ids($Query, $filters['items'] ?? []);

        $Query->group_by([$Query->table_label(), 'id']);

        $join_info = Worker::otm();
        $Query->join([$join_info['t'],$join_info['a']], [[$join_info['a'],'model_id', $Query->table_label(), 'id'],[$join_info['a'],'model_type', 'session']], 'LEFT OUTER');
        $Query->join([Worker::table_name(), 'session_workers'], [['session_workers','id', $join_info['a'], $join_info['k']]], 'LEFT OUTER');
        $Query->join([Operator::table_name(), 'operator'], [['session_workers','operator_id', 'operator', 'id']], 'LEFT OUTER');
        $Query->select_also(["GROUP_CONCAT(DISTINCT operator.name SEPARATOR ', ') as worker_names", "GROUP_CONCAT(DISTINCT session_workers.id SEPARATOR ', ') as worker_ids"]);

        $Query->auto_join(Note::table(), ['COUNT(DISTINCT note.id) as count_notes'], 'LEFT OUTER');

        if (isset($filters['service']) && !empty($filters['service']->get_id())) {
            $Query->aw_eq('service_id', $filters['service']->get_id(), $Query->table_label());
        }

        return $Query;
    }

    // TODO make that worth something.. gotta figure out bidirectional mapping
    public static function select_also()
    {
        return ['id','service_id','occured_on','label'];
    }
}
