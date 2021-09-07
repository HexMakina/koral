<?php

namespace HexMakina\koral\Controllers;

use \HexMakina\kadro\Auth\Operator;
use \HexMakina\Tempus\Dato;

class Home extends \HexMakina\kadro\Controllers\Kadro
{
    public function home()
    {
        return 'home/dashboard';
    }

    public function bootstrap()
    {
        $target_conroller = $this->get('HexMakina\Interfaces\RouterInterface')->targetController();
        $target_conroller = $this->get('Controllers\\'.$target_conroller);

        if (!$target_conroller->get('StateAgent')->hasFilter('date_start')) {
            $target_conroller->get('StateAgent')->filters('date_start', Dato::format($target_conroller->get('settings.app.time_window_start'), Dato::FORMAT));
        }

        if (!$target_conroller->get('StateAgent')->hasFilter('date_stop')) {
            $target_conroller->get('StateAgent')->filters('date_stop', Dato::format($target_conroller->get('settings.app.time_window_stop'), Dato::FORMAT));
        }

        $this->common_viewport($target_conroller);

        $target_conroller->execute();
    }

    public function common_viewport($target_conroller)
    {
        $all_operators = Operator::filter();
        $target_conroller->viewport('all_operators', $all_operators);
        $target_conroller->viewport('services', $target_conroller->get('Models\Service::class')::filter());
        $target_conroller->viewport('CurrentOperator', $this->get('HexMakina\Interfaces\Auth\OperatorInterface'));
    }
}
