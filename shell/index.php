<?php

use DI\Container;
use Symfony\Component\Console\Application;
use Tasks\Consumer\Product;

define('BASEDIR', dirname(__DIR__));

require_once BASEDIR . '/vendor/autoload.php';

$app = new Application();

$container = new Container();

include_once BASEDIR . '/config/services.php';

$app->add(new Product($container));

$app->run();