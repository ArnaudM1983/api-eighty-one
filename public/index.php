<?php

use App\Kernel;

// On force Symfony à croire qu'il est en HTTPS pour stopper les boucles 307
$_SERVER['HTTPS'] = 'on';

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};