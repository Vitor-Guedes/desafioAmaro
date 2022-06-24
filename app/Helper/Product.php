<?php

namespace App\Helper;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Slim\Psr7\UploadedFile;
use Psr\Container\ContainerInterface;

class Product
{
    protected $_container;

    protected $_db;

    protected $_rabbitConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
        $this->_db = $container->get('db');
    }

    /**
     * @param UploadedFile $uploaded
     * @param array $allowedExt
     * @return bool
     */
    public function validateExtFile(UploadedFile $uploaded, array $allowedExt = ['json'])
    {
        return in_array($this->getExt($uploaded), $allowedExt);
    }

    /**
     * @param UploadedFile $uploaded
     * @return string
     */
    public function getExt(UploadedFile $uploaded)
    {
        $fileName = $uploaded->getClientFilename();
        $pos = strpos($fileName, '.');
        return substr($fileName, $pos + 1);
    }

    /**
     * @param $uploaded
     * @return string
     */
    public function getContent(UploadedFile $uploaded)
    {
        return (string) $uploaded->getStream();
    }

    /**
     * @param UploadedFile $uploaded
     * @return array
     */
    public function getArrayContent(UploadedFile $uploaded)
    {
        $type = $this->getExt($uploaded);
        $method = 'convert' . ucfirst($type) . 'ToArray';
        if (method_exists($this, $method)) {
            return $this->$method($this->getContent($uploaded));
        }
        return [];
    }

    /**
     * @param string $content
     * @return array
     */
    public function convertJsonToArray(string $content = '')
    {
        return json_decode($content, true);
    }

    public function convertXmlToArray(string $content = '')
    {
        $xml = simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        return $this->convertJsonToArray($json);
    }

    public function insertProducts($products = [])
    {
        try {
            $this->_db::beginTransaction();

            if (isset($products['name'])) {
                $_products[] = $products;
                $products = $_products;
            }

            foreach ($products as $product) {
                $tags = $product['tags'];
                if (isset($tags['element'])) {
                    $tags = $tags['element'];
                }

                unset($product['tags']);

                $this->insertProduct($product);
                $this->insertTags($tags);

                $insertedProduct = $this->_db->table('product')->where('name', $product['name'])->first();

                $this->insertTagsProduct($tags, $insertedProduct->id);
            }

            $this->_db::commit();
            echo "ok!";
        } catch (Exception $e) {
            echo $e->getMessage();
            $this->_db::rollBack();
        }
    }

    public function insertProduct($product)
    {
        $this->_db->table('product')->insert($product);
    }

    public function insertTags($tags)
    {
        foreach ($tags as $tag) {
            $this->_db->table('tag')->updateOrInsert([
                'label' => $tag
            ]);
        }
    }

    public function insertTagsProduct($tags, $productId)
    {
        $table = 'tag';
        $tagsInBase = $this->_db->table($table)->whereIn('label', $tags)->get();

        $table = 'product_tag';
        foreach ($tagsInBase as $tag) {
            $this->_db->table($table)->insertOrIgnore([
                'product_id' => $productId,
                'tag_id' => $tag->id
            ]);
        }
    }

    public function sendToQueue($products)
    {
        $queueName = 'queue_product';
        $channel = $this->getConnection()->channel();
        $channel->queue_declare($queueName, false, true, false, false);

        foreach ($products as $product) {
            $tags = $product['tags'];
            if (isset($tags['element'])) {
                $tags = $tags['element'];
                $product['tags'] = [];
                $product['tags'] = $tags;
            }

            $data = json_encode($product);
            
            $message = new AMQPMessage($data, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT
            ]);

            $channel->basic_publish($message, '', $queueName);
        }

        $channel->close();
        $this->getConnection()->close();

        echo "ok!";
    }

    public function getConnection()
    {
        if (!$this->_rabbitConnection) {
            $settingsRabbit = $this->_container->get('settings')['rabbitmq'];
            $host = $settingsRabbit['host'];
            $port = $settingsRabbit['port'];
            $user = $settingsRabbit['user'];
            $pass = $settingsRabbit['pass'];
            $this->_rabbitConnection = new AMQPStreamConnection($host, $port, $user, $pass);
        }
        return $this->_rabbitConnection;
    }

    public function consumeQueue($output)
    {
        $queueName = 'queue_product';
        $channel = $this->getConnection()->channel();
        $channel->queue_declare($queueName, false, true, false, false);

        $proccess = function ($message) use ($output) {
            try {
                $this->_db::beginTransaction();
                $product = json_decode($message->body, true);

                $tags = $product['tags'];
                if (isset($tags['element'])) {
                    $tags = $tags['element'];
                }

                unset($product['tags']);

                $this->insertProduct($product);
                $this->insertTags($tags);

                $insertedProduct = $this->_db->table('product')->where('name', $product['name'])->first();

                $this->insertTagsProduct($tags, $insertedProduct->id);
                $this->_db::commit();
                $output->writeln($message->body . " - Product Add Successful!");

                $message->ack();
            } catch (Exception $e) {
                $output->writeln($e->getMessage());
                $this->_db::rollBack();
            }
        };

        $channel->basic_qos(null, 2, null);
        $channel->basic_consume($queueName, '', false, false, false, false, $proccess);

        while ($channel->is_open()) {
            $channel->wait();
        }

        $channel->close();
        $this->getConnection()->close();        
    }
}