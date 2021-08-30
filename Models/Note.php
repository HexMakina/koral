<?php

namespace HexMakina\koral\Models;

use HexMakina\Crudites\Interfaces\SelectInterface;
use HexMakina\TightORM\TightModel;
use HexMakina\LeMarchand\LeMarchand;

class Note extends TightModel implements Interfaces\ServiceEventInterface
{
    use Abilities\ServiceEvent;
    use Abilities\Itemability;
    use Abilities\Customerability;


    public function __toString()
    {
        preg_match("/(?:\w+(?:\W+|$)){0,5}/", $this->get('content'), $matches);
        return $matches[0] . '...';
    }

    public function immortal(): bool
    {
        if (count($this->item_ids()) > 0 || count(Customer::filter(['model' => $this])) > 0) {
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

        // $this->set_many([], Customer::otm());
        Customer::set_many([], $this);
        Item::set_many([], $this);
        // $this->set_many([], Item::otm());

        return parent::destroy($operator_id);
    }

    public static function first_for_customer_id($customer_id)
    {
        $fields = ['t_from.id', 't_from.occured_on', 't_from.service_id', 'service.abbrev as abbrev'];
        $table_alias = 't_from';
        $Query = static::table()->select($fields, $table_alias);

        //---- JOIN & FILTER  GAST
        $Query->join('service', [['service', 'id', $table_alias, 'service_id']], 'LEFT OUTER');
        $Query->join(['customers_models', 'gm'], [['gm', 'model_id', $table_alias, 'id'], ['gm', 'model_type', self::model_type()]], 'LEFT OUTER');
        $Query->join([Customer::table_name(), 'g'], [['g', 'id', 'gm', 'customer_id']], 'LEFT OUTER');

        $Query->aw_eq('customer_id', $customer_id, 'gm');
        $Query->order_by(['t_from', 'occured_on', 'ASC']);
        $Query->limit(1);

        $res = static::retrieve($Query);
        if ($res === false || empty($res)) {
            return null;
        }

        $res = array_pop($res);
        return [$res->abbrev => $res->occured_on];
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        //---- JOIN & FILTER SERVICE
        $Query = parent::query_retrieve($filters, $options);

        if (isset($filters['service']) && !empty($filters['service']->get_id())) {
            $Query->aw_eq('service_id', $filters['service']->get_id());
        }


        // //---- JOIN & FILTER SESSION
        if (isset($filters['session']) && !empty($filters['session']->get_id())) {
            $Query->aw_eq('session_id', $filters['session']->get_id());
        }

        if (isset($filters['note_type'])) {
            switch ($filters['note_type']) {
                case 'service':
                case 'interne':
                    $Query->aw_is_null('session_id');
                    break;

                case 'session':
                    $Query->aw_not_empty('session_id');
                    break;
            }
        }

        //---- JOIN & FILTER  Customer
        $customerClass = LeMarchand::box()->get('CustomerClass');
        $Query->join([$customerClass::otm('t'), 'gm'], [['gm', 'model_id', $Query->table_label(), 'id'], ['gm', 'model_type', 'note']], 'LEFT OUTER');
        $Query->join([$customerClass::table_name(), 'g'], [['g', 'id', 'gm', $customerClass::otm('k')]], 'LEFT OUTER');

        $Query->select_also([
          sprintf('GROUP_CONCAT(DISTINCT gm.%s) as %ss', $customerClass::otm('k'), $customerClass::otm('k')),
          sprintf('COUNT(DISTINCT gm.%s) as count_%ss', $customerClass::otm('k'), $customerClass::model_type()),
          sprintf('GROUP_CONCAT(DISTINCT g.name SEPARATOR ", ") as %s_names', $customerClass::otm('t'))
        ]);

        if (isset($filters['customer']) && !empty($filters['customer']->get_id())) {
            $Query->aw_eq('customer_id', $filters['customer']->get_id(), 'gm');
        }

        //---- JOIN & FILTER  ITEM
        if (isset($filters['items']) && !empty($filters['items'])) {
            $Query->join(['items_models', 'im'], [['im', 'model_id', $Query->table_label(), 'id'], ['im', 'model_type', self::model_type()]], 'INNER');
            $Query->aw_numeric_in('item_id', array_keys($filters['items']), 'im');
        }

        if (isset($filters['date_start'])) {
            $Query->aw_gte('occured_on', $filters['date_start'], $Query->table_label(), ':filter_date_start');
        }

        if (isset($filters['date_stop'])) {
            $Query->aw_lte('occured_on', $filters['date_stop'], $Query->table_label(), ':filter_date_stop');
        }

        $Query->group_by('id');
        if (!isset($options['order_by'])) {
            $Query->order_by(['occured_on', 'DESC']);
        }

        vd($Query);
        return $Query;
    }
}
