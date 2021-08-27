<?php

namespace App\Models\Abilities;

use \HexMakina\kadro\Models\Abilities\Event;

// implementation of SessionEventInterface
trait SessionEvent
{
  use ServiceEvent;
  
  public function event_session_field()
  {
    return 'session_id';
  }

  public function event_session()
  {
    return $this->get($this->event_session_field());
  }
}

?>
