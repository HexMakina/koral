<?php

namespace HexMakina\koral\Models;

use HexMakina\BlackBox\Database\SelectInterface;
use HexMakina\Crudites\CruditesException;
use HexMakina\TightORM\TightModel;
use HexMakina\kadro\Auth\Operator;

class Session extends TightModel implements Interfaces\ServiceEventInterface
{
    use \HexMakina\koral\Models\Abilities\ServiceEvent;
    use \HexMakina\koral\Models\Abilities\Itemability;

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
            return array_map('trim', explode(',', $this->get('worker_ids')));
        }

        if (array_search('1', $this->get('worker_ids'), true)) {
            return array_keys($this->get('worker_ids'));
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
                $worker_id = null;
                if (is_int($worker)) {
                    $worker_id = $worker;
                } elseif (is_object($worker)) {
                    $worker_id = $worker->getId();
                }

                try {
                    $record = self::inspect(Worker::otm()['t'])->produce(['model_type' => 'session', 'model_id' => $this->getId(), Worker::otm()['k'] => $worker_id]);
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

        $linked = Note::any(['session_id' => $this->getId()]); // can't have notes
        if (count($linked) > 0) {
            return true;
        }

        return false;
    }

    public function destroy($operator_id): bool
    {
        if ($this->immortal()) {
            return false;
        }
        Worker::setMany([], $this);

        return parent::destroy($operator_id);
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $Query = parent::query_retrieve($filters, $options);

        $Query = self::query_with_item_ids($Query, $filters['items'] ?? []);

        $Query->groupBy([$Query->tableLabel(), 'id']);

        $join_info = Worker::otm();
        $Query->join([$join_info['t'],$join_info['a']], [[$join_info['a'],'model_id', $Query->tableLabel(), 'id'],[$join_info['a'],'model_type', 'session']], 'LEFT OUTER');
        $Query->join([Worker::relationalMappingName(), 'session_workers'], [['session_workers','id', $join_info['a'], $join_info['k']]], 'LEFT OUTER');
        $Query->join([Operator::relationalMappingName(), 'operator'], [['session_workers','operator_id', 'operator', 'id']], 'LEFT OUTER');
        $Query->selectAlso(["GROUP_CONCAT(DISTINCT operator.name SEPARATOR ', ') as worker_names", "GROUP_CONCAT(DISTINCT session_workers.id SEPARATOR ', ') as worker_ids"]);

        $Query->autoJoin(Note::table(), ['COUNT(DISTINCT note.id) as count_notes'], 'LEFT OUTER');

        if (isset($filters['service']) && !empty($filters['service']->getId())) {
            $Query->whereEQ('service_id', $filters['service']->getId(), $Query->tableLabel());
        }

        return $Query;
    }

    // TODO make that worth something.. gotta figure out bidirectional mapping
    public static function selectAlso()
    {
        return ['id','service_id','occured_on','label'];
    }
}
