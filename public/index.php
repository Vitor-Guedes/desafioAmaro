<?php 

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

define('BASEDIR', dirname(__DIR__));
define('BASEDIR_VIEW', BASEDIR . '/views/');

require BASEDIR . '/vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, $args) {
    $renderer = new PhpRenderer(BASEDIR_VIEW);
    return $renderer->render($response, "index.phtml", $args);
});

$app->get('/products', function (Request $request, Response $response, $args) {
    $renderer = new PhpRenderer(BASEDIR_VIEW);
    return $renderer->render($response, "products.phtml", $args);
});

$app->run();