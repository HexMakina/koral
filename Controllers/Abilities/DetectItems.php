<?php

namespace HexMakina\koral\Controllers\Abilities;

use HexMakina\koral\Models\Item;

trait DetectItems
{
  //changes POST data to array, handling the no checkbox
    public function DetectItemsTraitor_before_save()
    {
        $item_ids = $this->router()->submitted()['item_ids'] ?? [];
        $this->form_model->set('item_ids', array_keys($item_ids));
    }

    public function DetectItemsTraitor_after_save()
    {
        if (!is_null($this->load_model) && method_exists($this->form_model, 'item_alterations') && $this->form_model->item_alterations($this->load_model)) {
            $this->logger()->nice(L('MODEL_LINKED_ALTERATIONS', [L('MODEL_item_INSTANCES')]));
        }
    }
}
