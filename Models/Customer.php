<?php

namespace HexMakina\koral\Models;

use HexMakina\TightORM\TightModel;
use HexMakina\TightORM\RelationManyToMany;
use HexMakina\Crudites\Interfaces\SelectInterface;
use HexMakina\TightORM\Interfaces\RelationManyToManyInterface;
use HexMakina\koral\Models\Interfaces\CustomerInterface;

class Customer extends TightModel implements RelationManyToManyInterface
{
    use RelationManyToMany;

    public function __toString()
    {
        $ret = $this->name();

        return $ret;
    }

    public function name(): string
    {
        $ret = $this->get('name');
        if ($this->is_alias()) {
            $ret .= ' [alias]';
        }

        return $ret;
    }

    // only alias can be deleted
    public function immortal(): bool
    {
        return $this->is_alias() === false;
    }

    // is the customer from pre-app epoch
    public function is_legacy()
    {
        return !empty($this->get('legacy'));
    }


    public function is_alias()
    {
        return !empty($this->get('alias_of'));
    }

    public static function make_alias_of($customer, $name = null): CustomerInterface
    {
        $ret = new Customer();

        $ret->set('alias_of', $customer->get_id());
        if (!is_null($name)) {
            $ret->set('name', $name);
        }

        return $ret;
    }

    public function first_contact_info()
    {
        $ret = ['first_contact_on' => null, 'first_contact_where' => null,'first_contact_where_details' => null,'model' => null];

        if ($this->is_legacy()) {
            $ret['first_contact_on'] = $this->get('first_contact_on');
            $ret['first_contact_where'] = $this->get('first_contact_where');
            $ret['first_contact_where_details'] = $this->get('first_contact_where_details');
        } elseif (!$this->is_new()) {
            $res = current(Note::filter(['customer' => $this], ['order_by' => 'occured_on ASC', 'limit' => [0,1]]));
            if ($res) {
                $ret['first_contact_on'] = $res->get('occured_on');
                $ret['first_contact_where'] = $res->get('service_abbrev');
                $ret['first_contact_where_details'] = 'MODEL_note_INSTANCE';
                $ret['model'] = $res;
            }
        }

        return $ret;
    }

    public function interactions($start_date, $stop_date)
    {
        $ret = [];

        $services = array_flip(Service::abbrevs());
        $pm_id = $services['PM'];

        $res = Note::filter(['customer' => $this, 'date_start' => $start_date, 'date_stop' => $stop_date]);
        foreach ($res as $r) {
            if ($r->event_service() == $pm_id) {
                continue;
            }

            if (!isset($ret[$r->event_service()])) {
                $ret[$r->event_service()] = 0;
            }
            ++$ret[$r->event_service()];
        }

        return $ret;
    }

    public static function by_name($name)
    {
        return current(self::by_names([$name]));
    }

    public static function by_names($names)
    {
        array_walk($names, function (&$value) {
            $value = trim($value);
        });

        $query = self::table()->select()->aw_string_in('name', $names);
        $customers = static::retrieve($query);
        foreach ($customers as $customer) { // search and replace aliases
            if ($customer->is_alias() && !is_null($original = static::exists($customer->get('alias_of')))) {
                $customers[$original->get_id()] = $original;
                unset($customers[$customer->get_id()]);
            }
        }
        return $customers;
    }

    public function listing_aliases()
    {
        if ($this->is_new()) {
            return [];
        }

        // $select_fields = ['g.*', "'' as last_fiche_accueil", "'' as count_fiche_accueil", "'' as last_fiche_donnee", "'' as count_fiche_donnee", "'' as last_note", "'' as last_note_id", "'' as count_note"];
        $Query = self::table()->select();
        $Query->and_where('alias_of <> id');
        $Query->aw_eq('alias_of', $this->get_id());

        return self::retrieve($Query);
    }

    public static function query_retrieve($filters = [], $options = []): SelectInterface
    {
        $Query = parent::query_retrieve($filters, $options);
        $Query->group_by([$Query->table_label(), 'id']);


        $Query->join([static::table(), 'customer_alias'], [['customer_alias', 'alias_of','customer','id']], 'LEFT OUTER');
        $Query->select_also('GROUP_CONCAT(DISTINCT customer_alias.name SEPARATOR ", ") as alias_names');

        $Query->join([self::otm('t'), 'customers_notes'], [['customers_notes', self::otm('k'), $Query->table_label(), 'id'],['customers_notes', 'model_type', 'note']], 'left outer');
        $Query->join([Note::table_name(), 'n'], [['customers_notes', 'model_id', 'n', 'id']], 'left outer');
        $Query->select_also(['MAX(n.occured_on) as last_note', 'COUNT(n.id) as count_note']);
    //
        if (isset($filters['items']) && !empty($filters['items'])) {
            $Query->and_where('1=0'); // TODO: this is a new low.. find another way to cancel query
            return $Query;
        }

        if (isset($filters['nolegacy'])) {
            $Query->aw_eq('legacy', 0);
        }

        if (isset($filters['medical'])) {
            if ($filters['medical'] === true) {
                $Query->aw_not_empty('id', 'fichemedicale');
            } else {
                $Query->aw_empty('id', 'fichemedicale');
            }
        }
        // dd($Query);

        if (isset($filters['model'])) {
            $model = $filters['model'];
            if ($model->is_new()) {
                $Query->and_where('1=0');
            }
            $Query->join([self::otm('t'), self::otm('a')], [[self::otm('a'), self::otm('k'), $Query->table_label(), 'id'], [self::otm('a'), 'model_type', get_class($model)::model_type()], [self::otm('a'), 'model_id', $model->get_id()]], 'inner');
        }
        if (!isset($options['order_by'])) {
                $Query->order_by('last_note DESC');
                $Query->order_by('last_fiche_donnee DESC');
                $Query->order_by('name ASC');
        }

        return $Query;
    }
}
