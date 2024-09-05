<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory;
use Psr\Log\LoggerInterface;

class InfoVirtualfixCommand extends Command
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
        $output->writeln('<info>Executing swissup:info:virtualfix command...</info>');

        try {
            $fixedThemesCount = $this->fixVirtualThemes();
            $this->logSuccess($fixedThemesCount);
            $output->writeln("<info>Successfully fixed $fixedThemesCount virtual theme(s).</info>");
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $this->logError($e);
            $output->writeln('<error>Error fixing virtual themes: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    private function fixVirtualThemes(): int
    {
        $virtualThemes = $this->collectionFactory->create()->addFieldToFilter('type', 1);
        $fixedCount = 0;

        foreach ($virtualThemes as $theme) {
            $theme->setType(0)->save();
            $fixedCount++;
        }

        return $fixedCount;
    }

    private function logSuccess(int $count): void
    {
        $this->logger->info("$count virtual theme(s) fixed successfully.");
    }

    private function logError(\Exception $e): void
    {
        $this->logger->error('Error fixing virtual themes: ' . $e->getMessage());
    }
}
