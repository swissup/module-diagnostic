<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

abstract class AbstractDisableModulesCommand extends AbstractModuleStateCommand
{
    /**
     * Command used to restore the modules disabled here.
     */
    abstract protected function getEnableCommandName(): string;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeCustomStyles($output);

        $scope = $this->getScopeLabel();
        $this->displayWelcomeBanner(
            $output,
            '🔴 DISABLE ' . strtoupper($scope) . ' MODULES',
            'Disabling All Active ' . $scope . ' Modules'
        );

        try {
            $allModules = $this->getManagedModules();

            if (empty($allModules)) {
                $output->writeln("<warning>⚠️  No $scope modules found in the system.</warning>");
                return Cli::RETURN_SUCCESS;
            }

            $enabledModules = $this->getEnabledModules($allModules);

            if (empty($enabledModules)) {
                $output->writeln("<warning>⚠️  All $scope modules are already disabled.</warning>");
                return Cli::RETURN_SUCCESS;
            }

            $this->displayModulesTable($output, $allModules, 'MODULES TO DISABLE');

            if (!$this->confirmAction($input, $output, 'disable', count($enabledModules))) {
                $output->writeln('<fg=yellow>⚠️  Operation cancelled by user.</>');
                return Cli::RETURN_SUCCESS;
            }

            $this->saveModulesState($enabledModules, $output);
            $this->applyModulesState(false, $enabledModules, $output);

            $this->displaySuccessBanner($output, "$scope modules disabled successfully!");

            $output->writeln('<fg=cyan>💡 To enable them back, run: bin/magento ' . $this->getEnableCommandName() . '</>');
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
     */
    private function displayModulesTable(OutputInterface $output, array $allModules, string $title): void
    {
        $this->displaySectionHeader($output, "📋 $title");

        $table = $this->createTable($output);
        $table->setHeaders(['<header>Module Name</header>', '<header>Current Status</header>']);
        $table->setColumnWidth(0, 40);
        $table->setColumnWidth(1, 15);

        foreach ($allModules as $moduleName) {
            $isEnabled = $this->moduleManager->isEnabled($moduleName);
            $status = $isEnabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';

            $table->addRow([
                "<comment>$moduleName</comment>",
                $status
            ]);
        }

        $table->render();
        $this->displaySectionSeparator($output);
    }
}
