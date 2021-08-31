<?php

namespace HexMakina\koral\Models;

use HexMakina\TightORM\TightModel;
use HexMakina\TightORM\RelationManyToMany;
use HexMakina\Crudites\Interfaces\SelectInterface;
use HexMakina\TightORM\Interfaces\RelationManyToManyInterface;
use HexMakina\kadro\Auth\OperatorInterface;
use HexMakina\kadro\Auth\Operatorability;

class Worker extends TightModel implements OperatorInterface, RelationManyToManyInterface
{
    use HexMakina\TightORM\RelationManyToMany;
    use Operatorability;

    public function __toString()
    {
        return '' . $this->name();
    }

    public static function by_group($collection = null, $active_only = false): array
    {
        $ret = [];
        $collection = $collection ?? self::filter();
        foreach ($collection as $worker) {
            if ($active_only && !$worker->is_active()) {
                continue;
            }
            if (!empty($worker->get('permission_names'))) {
                foreach (explode(',', $worker->permission_names) as $permission_name) {
                    if (strpos($permission_name, 'group_') === 0) {
                          $ret[$permission_name][$worker->get_id()] = $worker;
                    }
                }
            }
        }
        return $ret;
    }

    public static function inactive($collection = null)
    {
        $ret = [];

        $collection = $collection ?? self::filter();
        foreach ($collection as $worker) {
            if (!$worker->is_active()) {
                $ret[$worker->get_id()] = $worker;
            } elseif (empty($worker->get('permission_names'))) {
                $ret[$worker->get_id()] = $worker;
            }
        }

        return $ret;
    }

    public static function retrieve(SelectInterface $Query): array
    {
        $res = parent::retrieve($Query);

        foreach ($res as $worker) {
          // TODO move this to controller and ensure FETCH_PROPS_LATE is disabled
            if (property_exists($worker, 'permission_names')) {
                foreach (explode(',', $worker->permission_names) as $setter) {
                    $worker->set($setter, true);
                }
            }
        }
        return $res;
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $Query = parent::query_retrieve($filters, $options);
        $Query = Operatorability::enhance_query_retrieve($Query, $filters, $options);
      // dd($Query);
        $Query->group_by(['worker','id']);

        if (isset($filters['id'])) {
            $Query->aw_eq('id', $filters['id']);
        }

        if (isset($filters['model'])) {
            $model_type = get_class($filters['model'])::model_type();
            $join_alias = $model_type . '_workers';
            $Query->join([self::inspect('workers_models'),$join_alias], [[$join_alias,'worker_id','worker','id'],[$join_alias,'model_id',$filters['model']->get_id()],[$join_alias,'model_type',$model_type]], 'INNER');
        }

        $Query->order_by(['service_id','ASC']);

        return $Query;
    }
}
