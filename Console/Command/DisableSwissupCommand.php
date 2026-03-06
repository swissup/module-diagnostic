<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Magento\Framework\Console\Cli;
use Magento\Framework\Module\Status;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\DeploymentConfig;

class DisableSwissupCommand extends AbstractStyledCommand
{
    private Status $moduleStatus;
    private FullModuleList $fullModuleList;
    private ModuleManager $moduleManager;
    private DeploymentConfig $deploymentConfig;
    
    const STATE_FILE = 'var/swissup_modules_state.json';

    public function __construct(
        Status $moduleStatus,
        FullModuleList $fullModuleList,
        DeploymentConfig $deploymentConfig,
        ModuleManager $moduleManager
    ) {
        parent::__construct();
        $this->moduleStatus = $moduleStatus;
        $this->fullModuleList = $fullModuleList;
        $this->deploymentConfig = $deploymentConfig;
        $this->moduleManager = $moduleManager;
    }

    protected function configure()
    {
        $this->setName('swissup:info:disable-swissup')
             ->setDescription('Disable all currently enabled Swissup modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initializeCustomStyles($output);
        $this->displayWelcomeBanner($output, '🔴 DISABLE SWISSUP MODULES', 'Disabling All Active Swissup Modules');

        try {
            // Get all Swissup modules
            $allSwissupModules = $this->getAllSwissupModules();
            
            if (empty($allSwissupModules)) {
                $output->writeln('<warning>⚠️  No Swissup modules found in the system.</warning>');
                return Cli::RETURN_SUCCESS;
            }

            // Get currently enabled modules
            $enabledModules = $this->getEnabledSwissupModules($allSwissupModules);
            
            if (empty($enabledModules)) {
                $output->writeln('<warning>⚠️  All Swissup modules are already disabled.</warning>');
                return Cli::RETURN_SUCCESS;
            }

            // Display modules to be disabled
            $this->displayModulesTable($output, $allSwissupModules, 'MODULES TO DISABLE');

            // Confirm before disabling
            if (!$this->confirmAction($input, $output, count($enabledModules))) {
                $output->writeln('<fg=yellow>⚠️  Operation cancelled by user.</>');
                return Cli::RETURN_SUCCESS;
            }

            // Save current state before disabling
            $this->saveModulesState($enabledModules, $output);

            // Disable enabled modules
            $this->disableModules($enabledModules, $output);

            $this->displaySuccessBanner($output, 'Swissup modules disabled successfully!');
            
            $output->writeln('<fg=cyan>💡 To enable them back, run: bin/magento swissup:info:enable-swissup</>');
            $output->writeln('<fg=yellow>⚠️  Remember to run setup:upgrade and cache:flush after this operation!</>');
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ Error: " . $e->getMessage() . "</>");
            return Cli::RETURN_FAILURE;
        }
    }

    private function getAllSwissupModules(): array
    {
        $allModules = $this->fullModuleList->getNames();
        $swissupModules = [];
        
        foreach ($allModules as $moduleName) {
            if (strpos($moduleName, 'Swissup_') === 0) {
                $swissupModules[] = $moduleName;
            }
        }
        
        sort($swissupModules);
        return $swissupModules;
    }

    private function getEnabledSwissupModules(array $allSwissupModules): array
    {
        $enabledModules = [];
        
        foreach ($allSwissupModules as $moduleName) {
            if ($this->moduleManager->isEnabled($moduleName)) {
                $enabledModules[] = $moduleName;
            }
        }
        
        return $enabledModules;
    }

    private function displayModulesTable(OutputInterface $output, array $allModules, string $title)
    {
        $this->displaySectionHeader($output, "📋 $title");
        
        $table = new Table($output);
        $table->setHeaders(['<header>Module Name</header>', '<header>Current Status</header>']);
        $table->setStyle('box-double');
        $table->setColumnWidth(0, 40);
        $table->setColumnWidth(1, 15);

        foreach ($allModules as $moduleName) {
            $isEnabled = $this->moduleManager->isEnabled($moduleName);
            $status = $isEnabled ? '<fg=green>✅ Enabled</>' : '<fg=red>❌ Disabled</>';
            
            $table->addRow([
                "<comment>$moduleName</comment>",
                $status
            ]);
        }

        $table->render();
        $this->displaySectionSeparator($output);
    }

    private function saveModulesState(array $enabledModules, OutputInterface $output)
    {
        $this->displaySectionHeader($output, '💾 SAVING CURRENT STATE');
        
        $stateFile = BP . '/' . self::STATE_FILE;
        $stateData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'enabled_modules' => $enabledModules
        ];
        
        $dir = dirname($stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (file_put_contents($stateFile, json_encode($stateData, JSON_PRETTY_PRINT))) {
            $output->writeln("    <fg=white>│</> <success>✅ State saved to: " . self::STATE_FILE . "</success>");
            $output->writeln("    <fg=white>│</> <comment>📊 Enabled modules count: " . count($enabledModules) . "</comment>");
        } else {
            throw new \Exception("Failed to save state file: $stateFile");
        }
        
        $this->displaySectionSeparator($output);
    }

    private function disableModules(array $modules, OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🔄 DISABLING MODULES');
        
        $output->writeln("    <fg=white>│</> <comment>Disabling " . count($modules) . " module(s)...</comment>");
        $output->writeln('');
        
        try {
            $this->moduleStatus->setIsEnabled(false, $modules);
            
            foreach ($modules as $moduleName) {
                $output->writeln("    <fg=white>│</> <fg=red>❌</> <comment>$moduleName</comment>");
            }
            
            $output->writeln('');
            $output->writeln("    <fg=white>│</> <success>✅ All modules disabled successfully</success>");
        } catch (\Exception $e) {
            throw new \Exception("Failed to disable modules: " . $e->getMessage());
        }
        
        $this->displaySectionSeparator($output);
    }

    private function confirmAction(InputInterface $input, OutputInterface $output, int $moduleCount): bool
    {
        if ($input->getOption('no-interaction')) {
            return true;
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "<fg=yellow>⚠️  Are you sure you want to disable $moduleCount Swissup module(s)? [y/N] </>",
            false
        );

        return $helper->ask($input, $output, $question);
    }
}
