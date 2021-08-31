<?php

namespace HexMakina\koral\Models;

use HexMakina\TightORM\TightModel;
use \HexMakina\TightORM\RelationManyToMany;
use HexMakina\Crudites\Interfaces\SelectInterface;

class Item extends TightModel implements RelationManyToManyInterface
{
    use \HexMakina\TightORM\RelationManyToMany;

    const DEFAULT_RANK = 99;

    public function __toString()
    {
        return $this->get('label_fra') . '/' . $this->get('label_nld');
    }

    public function is_lieu()
    {
        return strpos($this->get('type') ?? '', 'lieu_') === 0;
    }


  // TODO fix it with foreign key and error messages ?
  // => try, fail, explain || prevent before query ?
    public function has_models(): bool
    {
        $Query = self::inspect(Item::otm('t'))->select(['item_id'])->aw_eq(Item::otm('k'), $this->get_id());
        return self::count($Query) > 0;
    }

    public function immortal(): bool
    {
        return $this->has_models();
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $Query = parent::query_retrieve();

        if (isset($filters['model'])) {
            $model = $filters['model'];

            $Query->join([self::otm('t'), self::otm('a')], [[self::otm('a'), self::otm('k'), $Query->table_label(), 'id']], 'INNER');
            $Query->aw_eq('model_id', $model->get_id(), Item::otm('a'));
            $Query->aw_eq('model_type', get_class($model)::model_type(), Item::otm('a'));
        }

        if (isset($filters['medical']) && !isset($filters['social'])) {
            $Query->and_where("type LIKE 'medical%' OR type='subjects'");
        } elseif (!isset($filters['medical']) && isset($filters['social'])) {
            $Query->aw_not_like('type', 'medical%');
        }

        if (isset($filters['type'])) {
            $Query->aw_eq('type', $filters['type']);
        }

        if (isset($filters['types'])) {
            $Query->aw_string_in('type', $filters['types']);
        }

        if (isset($filters['rank'])) {
            $Query->aw_eq('rank', $filters['rank']);
        }

        $Query->order_by(['type', 'ASC']);
        $Query->order_by(['rank', 'ASC']);
        $Query->order_by(['label_fra', 'ASC']);
        return $Query;
    }
}
