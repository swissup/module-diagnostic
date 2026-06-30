<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

abstract class AbstractEnableModulesCommand extends AbstractModuleStateCommand
{
    /**
     * Command that creates the state file consumed here.
     */
    abstract protected function getDisableCommandName(): string;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeCustomStyles($output);

        $scope = $this->getScopeLabel();
        $this->displayWelcomeBanner(
            $output,
            '🟢 ENABLE ' . strtoupper($scope) . ' MODULES',
            'Restoring Previously Enabled Modules'
        );

        try {
            $stateFile = BP . '/' . $this->getStateFile();

            if (!file_exists($stateFile)) {
                $output->writeln('<fg=red>❌ No state file found!</>');
                $output->writeln('<fg=yellow>⚠️  The state file is created when you run ' . $this->getDisableCommandName() . '</>');
                $output->writeln("<fg=cyan>💡 You need to disable $scope modules first before enabling them.</>");
                $output->writeln('');
                return Cli::RETURN_FAILURE;
            }

            $savedState = $this->loadModulesState($output);

            if (empty($savedState['enabled_modules'])) {
                $output->writeln('<warning>⚠️  No modules to enable. State file is empty.</warning>');
                return Cli::RETURN_SUCCESS;
            }

            $allModules = $this->getManagedModules();

            $this->displayModulesTable($output, $allModules, $savedState['enabled_modules'], 'MODULES TO ENABLE');

            if (!$this->confirmAction($input, $output, 'enable', count($savedState['enabled_modules']))) {
                $output->writeln('<fg=yellow>⚠️  Operation cancelled by user.</>');
                return Cli::RETURN_SUCCESS;
            }

            $this->applyModulesState(true, $savedState['enabled_modules'], $output);
            $this->removeStateFile($output);

            $this->displaySuccessBanner($output, "$scope modules enabled successfully!");

            $output->writeln('<fg=yellow>⚠️  Remember to run setup:upgrade and cache:flush after this operation!</>');
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ Error: " . $e->getMessage() . "</>");
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * @param string[] $allModules
     * @param string[] $toEnable
     */
    private function displayModulesTable(OutputInterface $output, array $allModules, array $toEnable, string $title): void
    {
        $this->displaySectionHeader($output, "📋 $title");

        $table = $this->createTable($output);
        $table->setHeaders(['<header>Module Name</header>', '<header>Current Status</header>', '<header>Will Enable</header>']);
        $table->setColumnWidth(0, 40);
        $table->setColumnWidth(1, 15);
        $table->setColumnWidth(2, 12);

        foreach ($allModules as $moduleName) {
            $isEnabled = $this->moduleManager->isEnabled($moduleName);
            $willEnable = in_array($moduleName, $toEnable);

            $status = $isEnabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';
            $willEnableText = $willEnable ? '<fg=green>Yes</>' : '<fg=gray>No</>';

            $table->addRow([
                "<comment>$moduleName</comment>",
                $status,
                $willEnableText
            ]);
        }

        $table->render();
        $this->displaySectionSeparator($output);
    }
}
