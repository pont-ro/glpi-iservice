<?php

use GlpiPlugin\Iservice\Controller\ClientController;
use GlpiPlugin\Iservice\Controller\HelloWorldController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
    $routes->add('iservice_client', '/client')
        ->controller(ClientController::class)
        ->methods(['GET', 'POST']);
};
