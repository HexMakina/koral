<?php

namespace HexMakina\koral\Controllers;

use \HexMakina\kadro\Auth\Operator;
use \HexMakina\Tempus\Dato;
use \HexMakina\koral\Models\{Service, Worker, Item};

class HomeController extends \HexMakina\kadro\Controllers\KadroController
{
    public function home()
    {
        return 'home/dashboard';
    }

    public function bootstrap()
    {
        $Controller = $this->box('RouterInterface')->target_controller();
        $Controller = $this->box($Controller);

        if (!$Controller->box('StateAgent')->hasFilter('date_start')) {
            $Controller->box('StateAgent')->filters('date_start', Dato::format($Controller->box('settings.app.time_window_start'), Dato::FORMAT));
        }

        if (!$Controller->box('StateAgent')->hasFilter('date_stop')) {
            $Controller->box('StateAgent')->filters('date_stop', Dato::format($Controller->box('settings.app.time_window_stop'), Dato::FORMAT));
        }

        $this->common_viewport($Controller);

        $Controller->execute();
    }

    public function common_viewport($Controller)
    {
        $all_operators = Operator::filter();
        $Controller->viewport('all_operators', $all_operators);
        $Controller->viewport('services', Service::filter());
        $Controller->viewport('CurrentOperator', $this->box('OperatorInterface'));
    }
}
