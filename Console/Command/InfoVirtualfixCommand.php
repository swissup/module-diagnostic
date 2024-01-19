<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory;
use Psr\Log\LoggerInterface;

class InfoVirtualFixCommand extends Command
{
    private CollectionFactory $collectionFactory;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('swissup:info:virtualfix')
             ->setDescription('Fix Virtual themes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $virtualThemes = $this->collectionFactory->create()->addFieldToFilter('type', 1);
            foreach ($virtualThemes as $theme) {
                $theme->setType(0)->save();
            }

            $this->logger->info('Virtual themes fixed successfully.');
            $output->writeln('<info>Executing swissup:info:virtualfix command...</info>');
            $output->writeln('<info>Virtual fix operations completed.</info>');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Error fixing virtual themes: ' . $e->getMessage());
            $output->writeln('<error>Error fixing virtual themes: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
