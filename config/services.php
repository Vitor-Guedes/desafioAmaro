<?php

use Slim\Views\PhpRenderer;
use \Illuminate\Database\Capsule\Manager;

$container->set('view', function () {
    return new PhpRenderer(BASEDIR_VIEW);
});

$container->set('settings', function () {
    return [
        'mysql' => [
            'determineRouteBeforeAppMiddleware' => false,
            'displayErrorDetails' => true,
            'db' => [
                'driver' => 'mysql',
                'host' => '198.22.1.5',
                'database' => 'default',
                'username' => 'root',
                'password' => 'root',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ]
        ],
        'rabbitmq' => [

        ]
    ];
});

$container->set('db', function ($c) {
    $settingsDb = $c->get('settings')['mysql']['db'];
    $capsule = new Manager;
    $capsule->addConnection($settingsDb);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
});