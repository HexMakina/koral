<?php

namespace HexMakina\koral\Controllers;

use HexMakina\kadro\Auth\Operator;
use HexMakina\Tempus\Dato;

class Home extends \HexMakina\kadro\Controllers\Kadro
{
    public function home()
    {
        return 'home/dashboard';
    }

    public function bootstrap()
    {
        $target_controller = $this->get('HexMakina\BlackBox\RouterInterface')->targetController();
        $target_controller = $this->get('Controllers\\' . $target_controller);

        if (!$target_controller->get('HexMakina\BlackBox\StateAgentInterface')->hasFilter('date_start')) {
            $target_controller->get('HexMakina\BlackBox\StateAgentInterface')->filters('date_start', Dato::format($target_controller->get('settings.app.time_window_start'), Dato::FORMAT));
        }

        if (!$target_controller->get('HexMakina\BlackBox\StateAgentInterface')->hasFilter('date_stop')) {
            $target_controller->get('HexMakina\BlackBox\StateAgentInterface')->filters('date_stop', Dato::format($target_controller->get('settings.app.time_window_stop'), Dato::FORMAT));
        }

        $this->common_viewport($target_controller);
        $target_controller->execute($this->get('HexMakina\BlackBox\RouterInterface')->targetMethod());
    }

    public function common_viewport($target_controller)
    {
        $all_operators = Operator::filter();
        $target_controller->viewport('all_operators', $all_operators);
        $target_controller->viewport('services', $target_controller->get('Models\Service::class')::filter());
        $target_controller->viewport('CurrentOperator', $this->get('HexMakina\BlackBox\Auth\OperatorInterface'));
    }
}
