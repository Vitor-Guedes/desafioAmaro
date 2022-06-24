<?php

namespace Tasks\Consumer;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Product
extends Command
{
    protected $_container;

    protected static $defaultName = 'app:consume-product';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();

        $this->_container = $container;
    }

    protected function configure()
    {
        $this->setHelp("This command executes the consume that registers the products...");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("\n Consulmindo fila para cadastro de produtos!\n");
        
        $helper = new \App\Helper\Product($this->_container);
        $helper->consumeQueue($output);

        return Command::SUCCESS;
    }
}