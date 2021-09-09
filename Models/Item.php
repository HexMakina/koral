<?php

namespace HexMakina\koral\Models;

use HexMakina\TightORM\TightModel;
use HexMakina\BlackBox\ORM\RelationManyToManyInterface;
use HexMakina\BlackBox\Database\SelectInterface;

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
        $Query = self::inspect(Item::otm('t'))->select(['item_id'])->whereEQ(Item::otm('k'), $this->getId());
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

            $Query->join([self::otm('t'), self::otm('a')], [[self::otm('a'), self::otm('k'), $Query->tableLabel(), 'id']], 'INNER');
            $Query->whereEQ('model_id', $model->getId(), Item::otm('a'));
            $Query->whereEQ('model_type', get_class($model)::model_type(), Item::otm('a'));
        }

        if (isset($filters['medical']) && !isset($filters['social'])) {
            $Query->where("type LIKE 'medical%' OR type='subjects'");
        } elseif (!isset($filters['medical']) && isset($filters['social'])) {
            $Query->whereNotLike('type', 'medical%');
        }

        if (isset($filters['type'])) {
            $Query->whereEQ('type', $filters['type']);
        }

        if (isset($filters['types'])) {
            $Query->whereStringIn('type', $filters['types']);
        }

        if (isset($filters['rank'])) {
            $Query->whereEQ('rank', $filters['rank']);
        }

        $Query->orderBy(['type', 'ASC']);
        $Query->orderBy(['rank', 'ASC']);
        $Query->orderBy(['label_fra', 'ASC']);
        return $Query;
    }
}
