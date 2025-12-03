<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\Console\Cli;
use Magento\Framework\Module\Status;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\DeploymentConfig;

class DisableThirdpartyCommand extends AbstractStyledCommand
{
    private Status $moduleStatus;
    private FullModuleList $fullModuleList;
    private ModuleManager $moduleManager;
    private DeploymentConfig $deploymentConfig;
    
    const STATE_FILE = 'var/thirdparty_modules_state.json';

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
        $this->setName('swissup:info:disable-thirdparty')
             ->setDescription('Disable all currently enabled 3rd-party modules (except Swissup_ and Magento_)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initializeCustomStyles($output);
        $this->displayWelcomeBanner($output, '🔴 DISABLE 3RD-PARTY MODULES', 'Disabling All Active 3rd-party Modules');

        try {
            // Get all 3rd-party modules (excluding Swissup_ and Magento_)
            $allThirdpartyModules = $this->getAllThirdpartyModules();
            
            if (empty($allThirdpartyModules)) {
                $output->writeln('<warning>⚠️  No 3rd-party modules found in the system.</warning>');
                return Cli::RETURN_SUCCESS;
            }

            // Get currently enabled modules
            $enabledModules = $this->getEnabledThirdpartyModules($allThirdpartyModules);
            
            if (empty($enabledModules)) {
                $output->writeln('<warning>⚠️  All 3rd-party modules are already disabled.</warning>');
                return Cli::RETURN_SUCCESS;
            }

            // Display modules to be disabled
            $this->displayModulesTable($output, $allThirdpartyModules, 'MODULES TO DISABLE');

            // Save current state before disabling
            $this->saveModulesState($enabledModules, $output);

            // Disable enabled modules
            $this->disableModules($enabledModules, $output);

            $this->displaySuccessBanner($output, '3rd-party modules disabled successfully!');
            
            $output->writeln('<fg=cyan>💡 To enable them back, run: bin/magento swissup:info:enable-thirdparty</>');
            $output->writeln('<fg=yellow>⚠️  Remember to run setup:upgrade and cache:flush after this operation!</>');
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ Error: " . $e->getMessage() . "</>");
            return Cli::RETURN_FAILURE;
        }
    }

    private function getAllThirdpartyModules(): array
    {
        $allModules = $this->fullModuleList->getNames();
        $thirdpartyModules = [];
        
        foreach ($allModules as $moduleName) {
            // Exclude Magento_ and Swissup_ modules
            if (strpos($moduleName, 'Magento_') !== 0 && strpos($moduleName, 'Swissup_') !== 0) {
                $thirdpartyModules[] = $moduleName;
            }
        }
        
        sort($thirdpartyModules);
        return $thirdpartyModules;
    }

    private function getEnabledThirdpartyModules(array $allThirdpartyModules): array
    {
        $enabledModules = [];
        
        foreach ($allThirdpartyModules as $moduleName) {
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
}
