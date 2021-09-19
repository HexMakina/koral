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
            $this->formModel()->set('rank', $this->modelClassName()::DEFAULT_RANK);
        }
    }



    public function hold()
    {
        $state_agent = $this->get('HexMakina\BlackBox\StateAgentInterface');
        
        if (!is_null($item = $this->modelClassName()::exists($this->router()->params()))) {
            $key = $item->is_lieu() ? 'lieu' : $item->type;

            if ($state_agent->filters($key) == $item->getId()) {
                $this->logger()->info($state_agent->filters('item_hold_label') . ' ' . $this->l('MODEL_item_NOTICE_RELEASED'));

                $state_agent->resetFilters($key);
                $state_agent->resetFilters('item_hold_id');
                $state_agent->resetFilters('item_hold_type');
                $state_agent->resetFilters('item_hold_id');
                $state_agent->resetFilters('item_hold_label');
            } else {
                $state_agent->filters($key, $item->getId());
                if ($item->is_lieu()) {
                    $state_agent->filters('item_hold_id', $item->getId());
                }
                $state_agent->filters('item_hold_type', $item->type);
                $state_agent->filters('item_hold_id', $item->getId());
                $state_agent->filters('item_hold_label', ucfirst($this->l('MODEL_item_TYPE_' . $item->type)) . ' "' . $item->get('label_fra') . '"');

                $this->logger()->info($state_agent->filters('item_hold_label') . ' ' . $this->l('MODEL_item_NOTICE_HELD'));
            }
        }

        $this->router()->hopBack();
    }
}
