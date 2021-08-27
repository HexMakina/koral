<?php

namespace HexMakina\koral\Models\Interfaces;

use HexMakina\kadro\Models\Interfaces\EventInterface;

// implementation in Models\Abilities\ServiceEvent
interface ServiceEventInterface extends EventInterface
{
  public function event_service();        // returns the service
  public function event_service_field();  // return the service field name
}
