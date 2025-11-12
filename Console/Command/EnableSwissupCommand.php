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

class EnableSwissupCommand extends AbstractStyledCommand
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
        $this->setName('swissup:info:enable-swissup')
             ->setDescription('Enable previously disabled Swissup modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initializeCustomStyles($output);
        $this->displayWelcomeBanner($output, '🟢 ENABLE SWISSUP MODULES', 'Restoring Previously Enabled Modules');

        try {
            // Check if state file exists
            $stateFile = BP . '/' . self::STATE_FILE;
            
            if (!file_exists($stateFile)) {
                $output->writeln('<fg=red>❌ No state file found!</>');
                $output->writeln('<fg=yellow>⚠️  The state file is created when you run swissup:info:disable-swissup</>');
                $output->writeln('<fg=cyan>💡 You need to disable Swissup modules first before enabling them.</>');
                $output->writeln('');
                return Cli::RETURN_FAILURE;
            }

            // Load saved state
            $savedState = $this->loadModulesState($stateFile, $output);
            
            if (empty($savedState['enabled_modules'])) {
                $output->writeln('<warning>⚠️  No modules to enable. State file is empty.</warning>');
                return Cli::RETURN_SUCCESS;
            }

            // Get all Swissup modules
            $allSwissupModules = $this->getAllSwissupModules();
            
            // Display what will be enabled
            $this->displayModulesTable($output, $allSwissupModules, $savedState['enabled_modules'], 'MODULES TO ENABLE');

            // Confirm before enabling
            if (!$this->confirmAction($input, $output, count($savedState['enabled_modules']))) {
                $output->writeln('<fg=yellow>⚠️  Operation cancelled by user.</>');
                return Cli::RETURN_SUCCESS;
            }

            // Enable modules
            $this->enableModules($savedState['enabled_modules'], $output);

            // Remove state file
            $this->removeStateFile($stateFile, $output);

            $this->displaySuccessBanner($output, 'Swissup modules enabled successfully!');
            
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

    private function loadModulesState(string $stateFile, OutputInterface $output): array
    {
        $this->displaySectionHeader($output, '📂 LOADING SAVED STATE');
        
        $stateContent = file_get_contents($stateFile);
        $savedState = json_decode($stateContent, true);
        
        if (!$savedState || !isset($savedState['enabled_modules'])) {
            throw new \Exception("Invalid state file format");
        }
        
        $output->writeln("    <fg=white>│</> <success>✅ State loaded from: " . self::STATE_FILE . "</success>");
        $output->writeln("    <fg=white>│</> <comment>📅 Saved on: " . ($savedState['timestamp'] ?? 'Unknown') . "</comment>");
        $output->writeln("    <fg=white>│</> <comment>📊 Modules to enable: " . count($savedState['enabled_modules']) . "</comment>");
        
        $this->displaySectionSeparator($output);
        
        return $savedState;
    }

    private function displayModulesTable(OutputInterface $output, array $allModules, array $toEnable, string $title)
    {
        $this->displaySectionHeader($output, "📋 $title");
        
        $table = new Table($output);
        $table->setHeaders(['<header>Module Name</header>', '<header>Current Status</header>', '<header>Will Enable</header>']);
        $table->setStyle('box-double');
        $table->setColumnWidth(0, 40);
        $table->setColumnWidth(1, 15);
        $table->setColumnWidth(2, 12);

        foreach ($allModules as $moduleName) {
            $isEnabled = $this->moduleManager->isEnabled($moduleName);
            $willEnable = in_array($moduleName, $toEnable);
            
            $status = $isEnabled ? '<fg=green>✅ Enabled</>' : '<fg=red>❌ Disabled</>';
            $willEnableText = $willEnable ? '<fg=green>✅ Yes</>' : '<fg=gray>—</>';
            
            $table->addRow([
                "<comment>$moduleName</comment>",
                $status,
                $willEnableText
            ]);
        }

        $table->render();
        $this->displaySectionSeparator($output);
    }

    private function confirmAction(InputInterface $input, OutputInterface $output, int $moduleCount): bool
    {
        if ($input->getOption('no-interaction')) {
            return true;
        }
        
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "<fg=yellow>⚠️  Are you sure you want to enable $moduleCount Swissup module(s)? [y/N] </>",
            false
        );
        
        return $helper->ask($input, $output, $question);
    }

    private function enableModules(array $modules, OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🔄 ENABLING MODULES');
        
        $output->writeln("    <fg=white>│</> <comment>Enabling " . count($modules) . " module(s)...</comment>");
        $output->writeln('');
        
        try {
            $this->moduleStatus->setIsEnabled(true, $modules);
            
            foreach ($modules as $moduleName) {
                $output->writeln("    <fg=white>│</> <fg=green>✅</> <comment>$moduleName</comment>");
            }
            
            $output->writeln('');
            $output->writeln("    <fg=white>│</> <success>✅ All modules enabled successfully</success>");
        } catch (\Exception $e) {
            throw new \Exception("Failed to enable modules: " . $e->getMessage());
        }
        
        $this->displaySectionSeparator($output);
    }

    private function removeStateFile(string $stateFile, OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🧹 CLEANUP');
        
        if (unlink($stateFile)) {
            $output->writeln("    <fg=white>│</> <success>✅ State file removed: " . self::STATE_FILE . "</success>");
        } else {
            $output->writeln("    <fg=white>│</> <warning>⚠️  Could not remove state file: " . self::STATE_FILE . "</warning>");
        }
        
        $this->displaySectionSeparator($output);
    }
}
