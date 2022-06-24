<?php 

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

define('BASEDIR', dirname(__DIR__));
define('BASEDIR_VIEW', BASEDIR . '/views/');

require_once BASEDIR . '/vendor/autoload.php';

// Create Container using PHP-DI
$container = new Container();

include_once BASEDIR . '/config/services.php';

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, $args) {
    return $this->get('view')->render($response, "index.phtml", $args);
});

$app->post('/insert', function (Request $request, Response $response, $args) {
    $uploadedFiles = $request->getUploadedFiles();
    $registerFile = $uploadedFiles['productRegisterFile'];

    $helper = new App\Helper\Product($this);
    $isValid = $helper->validateExtFile($registerFile, ['json', 'xml']);

    if (!$isValid) {
        $message = "Arquivo com extensão ({$helper->getExt($registerFile)}) inválida.";
        $response->getBody()->write($message);
        return $response;
    }

    $list = $helper->getArrayContent($registerFile);
    $products = array_shift($list);
    // $helper->insertProducts($products);
    $helper->sendToQueue($products);

    return $response;
});

$app->get('/products', function (Request $request, Response $response, $args) {
    $products = $this->get('db')
        ->table('product')
        ->leftJoin('product_tag', 'product.id', '=', 'product_tag.product_id')
        ->leftJoin('tag', 'tag.id', '=', 'product_tag.tag_id')
        ->get();

    $_products = [];
    foreach ($products as $product) {
        if (isset($_products[$product->product_id])) {
           
            if (!isset($_products[$product->product_id]['tags'][$product->tag_id])) {
                $_products[$product->product_id]['tags'][$product->tag_id] = [
                    'id' => $product->tag_id,
                    'label' => $product->label
                ];
            }

            continue ;
        }

        $_products[$product->product_id] = [
            'id' => $product->product_id,
            'name' => $product->name,
            'tags' => [
                $product->tag_id => [
                    'id' => $product->tag_id,
                    'label' => $product->label
                ]
            ]
        ];
    }

    return $this->get('view')->render($response, "products.phtml", ['products' => $_products]);
});

$app->run();