<?php

namespace HexMakina\koral\Controllers;

class Item extends \HexMakina\kadro\Controllers\ORM
{
    public function authorize($permission = null): bool
    {
        return parent::authorize('group_admin');
    }

    public function routeBack($route_name = null, $route_params = []): string
    {
        return $this->router()->hyp('item');
    }


    public function dashboard()
    {
        $filters = [];
        $this->listing(null, $filters);
        return 'item/listing';
    }

    public function before_edit()
    {
        if (is_null($this->formModel()->get('rank'))) {
            $this->formModel()->set('rank', Item::DEFAULT_RANK);
        }
    }



    public function hold()
    {
        if (!is_null($item = Item::exists($this->router()->params()))) {
            $key = $item->is_lieu() ? 'lieu' : $item->type;

            if ($this->get('StateAgent')->filters($key) == $item->getId()) {
                $this->logger()->info($this->get('StateAgent')->filters('item_hold_label') . ' ' . $this->l('MODEL_item_NOTICE_RELEASED'));

                $this->get('StateAgent')->resetFilters($key);
                $this->get('StateAgent')->resetFilters('item_hold_id');
                $this->get('StateAgent')->resetFilters('item_hold_type');
                $this->get('StateAgent')->resetFilters('item_hold_id');
                $this->get('StateAgent')->resetFilters('item_hold_label');
            } else {
                $this->get('StateAgent')->filters($key, $item->getId());
                if ($item->is_lieu()) {
                    $this->get('StateAgent')->filters('item_hold_id', $item->getId());
                }
                $this->get('StateAgent')->filters('item_hold_type', $item->type);
                $this->get('StateAgent')->filters('item_hold_id', $item->getId());
                $this->get('StateAgent')->filters('item_hold_label', ucfirst($this->l('MODEL_item_TYPE_' . $item->type)) . ' "' . $item->get('label_fra') . '"');

                $this->logger()->info($this->get('StateAgent')->filters('item_hold_label') . ' ' . $this->l('MODEL_item_NOTICE_HELD'));
            }
        }

        $this->router()->hopBack();
    }
}
