<?php

namespace HexMakina\koral\Controllers;

use HexMakina\koral\Models\Item;

class ItemController extends \HexMakina\kadro\Controllers\ORMController
{
    public function authorize($permission = null): bool
    {
        return parent::authorize('group_admin');
    }

    public function route_back($route_name = null, $route_params = []): string
    {
        return $this->router()->prehop('item');
    }


    public function dashboard()
    {
        $filters = [];
        $this->listing(null, $filters);
        return 'item/listing';
    }

    public function before_edit()
    {
        if (is_null($this->form_model->get('rank'))) {
            $this->form_model->set('rank', Item::DEFAULT_RANK);
        }
    }



    public function hold()
    {
        if (!is_null($item = Item::exists($this->router()->params()))) {
            $key = $item->is_lieu() ? 'lieu' : $item->type;

            if ($this->box('StateAgent')->filters($key) == $item->get_id()) {
                $this->logger()->info($this->box('StateAgent')->filters('item_hold_label') . ' ' . L('MODEL_item_NOTICE_RELEASED'));

                $this->box('StateAgent')->resetFilters($key);
                $this->box('StateAgent')->resetFilters('item_hold_id');
                $this->box('StateAgent')->resetFilters('item_hold_type');
                $this->box('StateAgent')->resetFilters('item_hold_id');
                $this->box('StateAgent')->resetFilters('item_hold_label');
            } else {
                $this->box('StateAgent')->filters($key, $item->get_id());
                if ($item->is_lieu()) {
                    $this->box('StateAgent')->filters('item_hold_id', $item->get_id());
                }
                $this->box('StateAgent')->filters('item_hold_type', $item->type);
                $this->box('StateAgent')->filters('item_hold_id', $item->get_id());
                $this->box('StateAgent')->filters('item_hold_label', ucfirst(L('MODEL_item_TYPE_' . $item->type)) . ' "' . $item->get('label_fra') . '"');

                $this->logger()->info($this->box('StateAgent')->filters('item_hold_label') . ' ' . L('MODEL_item_NOTICE_HELD'));
            }
        }

        $this->router()->hop_back();
    }
}
