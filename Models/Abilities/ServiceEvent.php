<?php

namespace HexMakina\koral\Models\Abilities;

use \HexMakina\kadro\Models\Abilities\Event;

// implementation of ServiceEventInterface
trait ServiceEvent
{
    use Event;

    abstract public function get($prop_name);

    public function event_service_field()
    {
        return 'service_id';
    }

    public function event_service()
    {
        return $this->get($this->event_service_field());
    }
}
