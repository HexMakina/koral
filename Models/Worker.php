<?php

namespace HexMakina\koral\Models;

use HexMakina\TightORM\TightModel;
use HexMakina\BlackBox\ORM\RelationManyToManyInterface;
use HexMakina\BlackBox\Database\SelectInterface;
use HexMakina\BlackBox\Auth\OperatorInterface;
use HexMakina\kadro\Auth\Operatorability;

class Worker extends TightModel implements OperatorInterface, RelationManyToManyInterface
{
    use \HexMakina\TightORM\RelationManyToMany;
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
            if ($active_only && !$worker->isActive()) {
                continue;
            }
            if (!empty($worker->get('permission_names'))) {
                foreach (explode(',', $worker->permission_names) as $permission_name) {
                    if (strpos($permission_name, 'group_') === 0) {
                          $ret[$permission_name][$worker->getId()] = $worker;
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
            if (!$worker->isActive()) {
                $ret[$worker->getId()] = $worker;
            } elseif (empty($worker->get('permission_names'))) {
                $ret[$worker->getId()] = $worker;
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

    public static function safeLoading($op_id): OperatorInterface
    {
      return static::retrieve(static::query_retrieve(['id' => $op_id]));
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $Query = parent::query_retrieve($filters, $options);
        $Query = Operatorability::enhance_query_retrieve($Query, $filters, $options);
      // dd($Query);
        $Query->groupBy(['worker','id']);

        if (isset($filters['id'])) {
            $Query->whereEQ('id', $filters['id']);
        }

        if (isset($filters['model'])) {
            $model_type = get_class($filters['model'])::model_type();
            $join_alias = $model_type . '_workers';
            $Query->join([self::inspect('workers_models'),$join_alias], [[$join_alias,'worker_id','worker','id'],[$join_alias,'model_id',$filters['model']->getId()],[$join_alias,'model_type',$model_type]], 'INNER');
        }

        $Query->orderBy(['service_id','ASC']);

        return $Query;
    }
}
