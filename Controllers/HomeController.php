<?php

namespace HexMakina\koral\Controllers;

use HexMakina\kadro\Auth\Operator;
use HexMakina\Tempus\Dato;
use HexMakina\koral\Models\Service;

class HomeController extends \HexMakina\kadro\Controllers\KadroController
{
    public function home()
    {
        return 'home/dashboard';
    }

    public static function bootstrap($Controller, $Operator)
    {
        if (!$Controller->box('StateAgent')->hasFilter('date_start')) {
            $Controller->box('StateAgent')->filters('date_start', Dato::format($Controller->box('settings.app.time_window_start'), Dato::FORMAT));
        }

        if (!$Controller->box('StateAgent')->hasFilter('date_stop')) {
            $Controller->box('StateAgent')->filters('date_stop', Dato::format($Controller->box('settings.app.time_window_stop'), Dato::FORMAT));
        }

        self::common_viewport($Controller, $Operator);


        $Controller->execute();
    }


    public static function common_viewport($Controller, $Operator)
    {
        $all_operators = Operator::filter();
        $Controller->viewport('all_operators', $all_operators);

        $Controller->viewport('services', Service::filter());
        $Controller->viewport('CurrentOperator', $Operator);
    }
}
