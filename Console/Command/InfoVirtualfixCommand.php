<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
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
        // Initialize custom styles
        $this->initializeCustomStyles($output);
        
        $this->displayWelcomeBanner($output);

        try {
            $this->displayVirtualThemesBefore($output);
            $fixedThemesCount = $this->fixVirtualThemes($output);
            $this->logSuccess($fixedThemesCount);
            $this->displaySuccessResult($output, $fixedThemesCount);
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $this->logError($e);
            $this->displayErrorResult($output, $e);
            return Cli::RETURN_FAILURE;
        }
    }

    private function initializeCustomStyles(OutputInterface $output)
    {
        $outputFormatter = $output->getFormatter();
        $outputFormatter->setStyle('header', new OutputFormatterStyle('cyan', null, ['bold']));
        $outputFormatter->setStyle('success', new OutputFormatterStyle('green', null, ['bold']));
        $outputFormatter->setStyle('warning', new OutputFormatterStyle('yellow', null, ['bold']));
        $outputFormatter->setStyle('highlight', new OutputFormatterStyle('white', 'blue', ['bold']));
    }
    
    private function displayWelcomeBanner(OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>┌─────────────────────────────────────────────────────────────┐</>');
        $output->writeln('<fg=cyan>│</> <fg=white;bg=blue>             🔧 VIRTUAL THEME FIXER              </> <fg=cyan>│</>');
        $output->writeln('<fg=cyan>│</> <fg=white;bg=blue>          Fixing Virtual Theme Issues            </> <fg=cyan>│</>');
        $output->writeln('<fg=cyan>└─────────────────────────────────────────────────────────────┘</>');
        $output->writeln('');
    }
    
    private function displayVirtualThemesBefore(OutputInterface $output)
    {
        $output->writeln('<header>╭─── 🔍 SCANNING FOR VIRTUAL THEMES ───╮</header>');
        $output->writeln('');
        
        $virtualThemes = $this->collectionFactory->create()->addFieldToFilter('type', 1);
        $virtualCount = $virtualThemes->count();
        
        if ($virtualCount === 0) {
            $output->writeln('    <success>✅ No virtual themes found - system is healthy!</success>');
            $output->writeln('');
            return;
        }
        
        $output->writeln("    <warning>⚠️  Found $virtualCount virtual theme(s) that need fixing:</warning>");
        $output->writeln('');
        
        $table = new Table($output);
        $table->setHeaders(['<header>ID</header>', '<header>Theme Title</header>', '<header>Status</header>']);
        $table->setStyle('box-double');
        $table->setColumnWidth(0, 4);
        $table->setColumnWidth(1, 25);
        $table->setColumnWidth(2, 13);
        
        foreach ($virtualThemes as $theme) {
            $table->addRow([
                '<fg=red>' . $theme->getId() . '</>',
                '<fg=red>' . $theme->getThemeTitle() . '</>',
                '<fg=red>❌ Virtual</>'
            ]);
        }
        
        $table->render();
        $output->writeln('');
    }

    private function fixVirtualThemes(OutputInterface $output): int
    {
        $output->writeln('<header>╭─── 🔧 FIXING VIRTUAL THEMES ───╮</header>');
        $output->writeln('');
        
        $virtualThemes = $this->collectionFactory->create()->addFieldToFilter('type', 1);
        $fixedCount = 0;

        foreach ($virtualThemes as $theme) {
            $output->writeln("    <fg=cyan>│</> <comment>Fixing theme: {$theme->getThemeTitle()}</comment>");
            $theme->setType(0)->save();
            $fixedCount++;
            $output->writeln("    <fg=green>│</> <success>✅ Theme fixed successfully</success>");
        }

        $output->writeln('');
        return $fixedCount;
    }
    
    private function displaySuccessResult(OutputInterface $output, int $count)
    {
        $output->writeln('<header>╭─── 🎉 OPERATION COMPLETED ───╮</header>');
        $output->writeln('');
        
        if ($count === 0) {
            $output->writeln('    <success>✅ No virtual themes were found to fix</success>');
        } else {
            $output->writeln("    <success>✅ Successfully fixed $count virtual theme(s)!</success>");
            $output->writeln('    <fg=cyan>💡 Run "bin/magento swissup:info" to verify the changes</fg=cyan>');
        }
        
        $output->writeln('');
        $output->writeln('<fg=green>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $output->writeln('');
    }
    
    private function displayErrorResult(OutputInterface $output, \Exception $e)
    {
        $output->writeln('');
        $output->writeln('<fg=red>┌─────────────────────────────────────────────────────────────┐</>');
        $output->writeln('<fg=red>│</> <fg=white;bg=red>                    ❌ ERROR                        </> <fg=red>│</>');
        $output->writeln('<fg=red>└─────────────────────────────────────────────────────────────┘</>');
        $output->writeln('');
        $output->writeln('<fg=red>❌ Error fixing virtual themes: ' . $e->getMessage() . '</>');
        $output->writeln('');
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
