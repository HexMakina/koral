<?php

namespace HexMakina\koral\Models\Abilities;

use HexMakina\koral\Models\Item;

trait Itemability
{
    public $items = null;
  // public $item_ids = null;

    abstract public function is_new(): bool;
    abstract public function get($prop_name);

    public function item_ids(): array
    {
        if (is_null($this->get('item_ids'))) {
            if ($this->is_new()) {
                return [];
            }

          // no item_ids, but not a new record.. let's load & set
            $this->set('items', Item::filter(['model' => $this]));
            $this->set('item_ids', array_keys($this->get('items')));
        } elseif (!is_array($this->get('item_ids')) && !empty($this->get('item_ids'))) { // loaded from database
            $this->set('item_ids', explode(',', $this->get('item_ids')));
        }

        return $this->get('item_ids');
    }

    public function items()
    {
        if (is_array($this->item_ids()) && count($this->item_ids()) === 0) {
            $this->items = [];
        } elseif (is_null($this->items)) {
            $this->items = Item::get_many_by_AIPK($this->item_ids());
        }

        return $this->items;
    }

    public function item_alterations($other_model): bool
    {
        if (!is_null($other_model)) {
            return count($this->item_ids()) != count($other_model->item_ids()) || !empty(array_diff($this->item_ids(), $other_model->item_ids()));
        }

        return false;
    }

    public function ItemabilityTraitor_after_save()
    {
        $res = Item::set_many_by_ids($this->item_ids(), $this);
      // $res = $this->set_many_by_ids($this->item_ids(), Item::otm());

        if ($res === true) {
            return 'ITEM_ITEMABILITY_CHANGES';
        }
        return $res;
    }

    public function ItemabilityTraitor_before_destroy()
    {
        Item::set_many([], $this);
    }

    public static function query_with_item_ids($Query, $restrict_items = [])
    {
        $Query->group_by('id'); //TODO replace id by auto-pk-detection
        $Query->select_also(['GROUP_CONCAT(DISTINCT im.item_id) as item_ids', 'COUNT(DISTINCT im.item_id) as count_items']);
        $Query->join(['items_models', 'im'], [['im', 'model_id', $Query->table_label(), 'id'], ['im', 'model_type', static::model_type()]], 'LEFT OUTER');

        if (!empty($restrict_items)) {
            $Query->aw_numeric_in('item_id', array_keys($restrict_items), 'im');
        }

        return $Query;
    }
}
