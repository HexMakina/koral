<?php

namespace HexMakina\koral\Controllers;

use \HexMakina\kadro\Auth\Operator;
use \HexMakina\Tempus\Dato;

class HomeController extends \HexMakina\kadro\Controllers\KadroController
{
    public function home()
    {
        return 'home/dashboard';
    }

    public function bootstrap()
    {
        $Controller = $this->get('RouterInterface')->target_controller();
        $Controller = $this->get($Controller);

        if (!$Controller->get('StateAgent')->hasFilter('date_start')) {
            $Controller->get('StateAgent')->filters('date_start', Dato::format($Controller->get('settings.app.time_window_start'), Dato::FORMAT));
        }

        if (!$Controller->get('StateAgent')->hasFilter('date_stop')) {
            $Controller->get('StateAgent')->filters('date_stop', Dato::format($Controller->get('settings.app.time_window_stop'), Dato::FORMAT));
        }

        $this->common_viewport($Controller);

        $Controller->execute();
    }

    public function common_viewport($Controller)
    {
        $all_operators = Operator::filter();
        $Controller->viewport('all_operators', $all_operators);
        $Controller->viewport('services', $Controller->get('ServiceClass')::filter());
        $Controller->viewport('CurrentOperator', $this->get('OperatorInterface'));
    }
}
