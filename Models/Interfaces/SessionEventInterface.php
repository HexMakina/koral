<?php

namespace HexMakina\koral\Models\Interfaces;

// implementation in Models\Abilities\SessionEvent
interface SessionEventInterface extends ServiceEventInterface
{
  public function event_session_field();
  public function event_session();
}

?>
