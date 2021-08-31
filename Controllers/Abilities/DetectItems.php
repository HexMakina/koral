<?php

namespace HexMakina\koral\Controllers\Abilities;

use \HexMakina\LogLaddy\LoggerInterface;
use \HexMakina\Hopper\RouterInterface;
use \HexMakina\koral\Models\Item;

/** detect POST items_ids and sets the form model */
/** also prints a nice message if alterations have been detected */

trait DetectItems
{
    abstract public function router(): RouterInterface;
    abstract public function logger(): LoggerInterface;

  //changes POST data to array, handling the no checkbox
    public function DetectItemsTraitor_before_save()
    {
        $item_ids = $this->router()->submitted()['item_ids'] ?? [];
        $this->formModel()->set('item_ids', array_keys($item_ids));
    }

    public function DetectItemsTraitor_after_save()
    {
        if ($this->DetectItems_hasAlterations()) {
            $this->logger()->nice($this->l('MODEL_LINKED_ALTERATIONS', [$this->l('MODEL_item_INSTANCES')]));
        }
    }

    private function DetectItems_hasAlterations()
    {
      return method_exists($this->formModel(), 'item_alterations') && $this->formModel()->item_alterations($this->load_model);
    }
}
