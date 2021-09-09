<?php

namespace HexMakina\koral\Models;

use HexMakina\BlackBox\Database\SelectInterface;
use HexMakina\TightORM\TightModel;
use HexMakina\LeMarchand\LeMarchand;

class Note extends TightModel implements Interfaces\ServiceEventInterface
{
    use \HexMakina\koral\Models\Abilities\ServiceEvent;
    use \HexMakina\koral\Models\Abilities\Itemability;
    use \HexMakina\koral\Models\Abilities\Customerability;


    public function __toString()
    {
        preg_match("/(?:\w+(?:\W+|$)){0,5}/", $this->get('content'), $matches);
        return $matches[0] . '...';
    }

    public function immortal(): bool
    {
        if (count($this->item_ids()) > 0 || count(LeMarchand::box()->get('Models\Customer::class')::filter(['model' => $this])) > 0) {
            return true;
        }

        return false;
    }

    public function item_types()
    {
        return ['subjects'];
    }

    public function before_save(): array
    {
        if (empty($this->get('todo'))) { // goddamn checkboxes
            $this->set('todo', 0);
        }

        return [];
    }

    public function destroy($operator_id): bool
    {
        if ($this->immortal()) {
            return false;
        }

        // $this->setMany([], Customer::otm());
        $customer_class = LeMarchand::box()->get('Models\Customer::class');
        $customer_class::setMany([], $this);

        $item_class = LeMarchand::box()->get('Models\Item::class');
        $item_class::setMany([], $this);

        return parent::destroy($operator_id);
    }

    // public static function first_for_customer_id($customer_id)
    // {
    //     $fields = ['t_from.id', 't_from.occured_on', 't_from.service_id', 'service.abbrev as abbrev'];
    //     $table_alias = 't_from';
    //     $Query = static::table()->select($fields, $table_alias);
    //
    //     //---- JOIN & FILTER  GAST
    //     $Query->join('service', [['service', 'id', $table_alias, 'service_id']], 'LEFT OUTER');
    //
    //     $customerClass = LeMarchand::box()->get('Models\Customer::class');
    //     $Query->join([$customerClass::otm('t'), 'gm'], [['gm', 'model_id',  $table_alias, 'id'], ['gm', 'model_type', self::model_type()]], 'LEFT OUTER');
    //     $Query->join([$customerClass::relationalMappingName(), 'g'], [['g', 'id', 'gm', $customerClass::otm('k')]], 'LEFT OUTER');
    //
    //     // $Query->join(['customers_models', 'gm'], [['gm', 'model_id', $table_alias, 'id'], ['gm', 'model_type', self::model_type()]], 'LEFT OUTER');
    //     // $Query->join([LeMarchand::box()->get('Models\Customer::class')::relationalMappingName(), 'g'], [['g', 'id', 'gm', 'customer_id']], 'LEFT OUTER');
    //
    //     $Query->whereEQ('customer_id', $customer_id, 'gm');
    //     $Query->orderBy(['t_from', 'occured_on', 'ASC']);
    //     $Query->limit(1);
    //
    //     $res = static::retrieve($Query);
    //     if ($res === false || empty($res)) {
    //         return null;
    //     }
    //
    //     $res = array_pop($res);
    //     return [$res->abbrev => $res->occured_on];
    // }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        //---- JOIN & FILTER SERVICE
        $Query = parent::query_retrieve($filters, $options);

        if (isset($filters['service']) && !empty($filters['service']->getId())) {
            $Query->whereEQ('service_id', $filters['service']->getId(), $Query->tableAlias());
        }


        // //---- JOIN & FILTER SESSION
        if (isset($filters['session']) && !empty($filters['session']->getId())) {
            $Query->whereEQ('session_id', $filters['session']->getId(), $Query->tableAlias());
        }

        if (isset($filters['note_type'])) {
            switch ($filters['note_type']) {
                case 'service':
                case 'interne':
                    $Query->whereIsNull('session_id');
                    break;

                case 'session':
                    $Query->whereNotEmpty('session_id');
                    break;
            }
        }

        //---- JOIN & FILTER  Customer
        $customerClass = LeMarchand::box()->get('Models\Customer::class');
        $Query->join([$customerClass::otm('t'), 'gm'], [['gm', 'model_id', $Query->tableLabel(), 'id'], ['gm', 'model_type', 'note']], 'LEFT OUTER');
        $Query->join([$customerClass::relationalMappingName(), 'g'], [['g', 'id', 'gm', $customerClass::otm('k')]], 'LEFT OUTER');


        $Query->selectAlso([
          sprintf('GROUP_CONCAT(DISTINCT gm.%s) as %ss', $customerClass::otm('k'), $customerClass::otm('k')),
          sprintf('COUNT(DISTINCT gm.%s) as count_%ss', $customerClass::otm('k'), $customerClass::model_type()),
          sprintf('GROUP_CONCAT(DISTINCT g.name SEPARATOR ", ") as %s_names', $customerClass::model_type())
        ]);

        if (isset($filters[$customerClass::model_type()]) && !empty($filters[$customerClass::model_type()]->getId())) {
            $Query->whereEQ($customerClass::otm('k'), $filters[$customerClass::model_type()]->getId(), 'gm');
        }

        //---- JOIN & FILTER  ITEM
        if (isset($filters['items']) && !empty($filters['items'])) {
            $Query->join(['items_models', 'im'], [['im', 'model_id', $Query->tableLabel(), 'id'], ['im', 'model_type', self::model_type()]], 'INNER');
            $Query->whereNumericIn('item_id', array_keys($filters['items']), 'im');
        }

        if (isset($filters['date_start'])) {
            $Query->whereGTE('occured_on', $filters['date_start'], $Query->tableLabel(), ':filter_date_start');
        }

        if (isset($filters['date_stop'])) {
            $Query->whereLTE('occured_on', $filters['date_stop'], $Query->tableLabel(), ':filter_date_stop');
        }

        $Query->groupBy('id');
        if (!isset($options['order_by'])) {
            $Query->orderBy(['occured_on', 'DESC']);
        }
        return $Query;
    }
}
