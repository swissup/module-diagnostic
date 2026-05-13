<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\Console\Cli;
use Magento\Framework\Shell;
use Magento\Framework\App\DeploymentConfig;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;

class InfoCommand extends AbstractStyledCommand
{
    private Shell $shell;
    private DeploymentConfig $deploymentConfig;
    private BackendUrlInterface $backendUrl;
    private ThemeCollectionFactory $themeCollectionFactory;

    public function __construct(
        Shell $shell,
        DeploymentConfig $deploymentConfig,
        BackendUrlInterface $backendUrl,
        ThemeCollectionFactory $themeCollectionFactory
    ) {
        parent::__construct();
        $this->shell = $shell;
        $this->deploymentConfig = $deploymentConfig;
        $this->backendUrl = $backendUrl;
        $this->themeCollectionFactory = $themeCollectionFactory;
    }

    protected function configure()
    {
        $this->setName('swissup:info')
             ->setDescription('Store environment information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Initialize custom styles
        $this->initializeCustomStyles($output);
        
        try {
            $this->displayWelcomeBanner($output, '🔍 SWISSUP DIAGNOSTIC TOOL', 'Analyzing Magento 2 Environment');
            $this->outputEnvironmentInfo($input, $output);
            $this->checkClientOverrides($output);
            $this->outputDatabaseInfo($output);
            $this->outputBackendUrl($output);
            $this->outputMagentoThemeData($input, $output);
            $this->displaySuccessBanner($output, 'Diagnostic analysis completed successfully!');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ Error: " . $e->getMessage() . "</>");
            return Cli::RETURN_FAILURE;
        }
    }

    private function outputEnvironmentInfo(InputInterface $input, OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🔧 ENVIRONMENT INFORMATION');
        
        $commands = [
            'php_version' => ['command' => 'php -v | head -n 1 && whereis php', 'icon' => '🐘', 'title' => 'PHP Version'],
            'magento_version' => ['command' => 'php bin/magento --version', 'icon' => '🛍️', 'title' => 'Magento Version'],
            'composer_version' => ['command' => 'composer --version && whereis composer', 'icon' => '📦', 'title' => 'Composer Version'],
            'nginx_user' => ['command' => 'whoami', 'icon' => '👤', 'title' => 'System User'],
        ];

        foreach ($commands as $key => $data) {
            $this->getCommandInfo($input, $output, $data['command'], $data['icon'] . ' ' . $data['title']);
        }
        
        $this->displaySectionSeparator($output);
    }

    private function checkClientOverrides(OutputInterface $output)
    {
        $this->displaySectionHeader($output, '📂 FOLDER STRUCTURE CHECK');
        
        $folderPaths = [
            ['path' => 'app/code/Swissup/', 'type' => 'Swissup Modules', 'icon' => '🏗️'],
            ['path' => 'app/design/frontend/Swissup/', 'type' => 'Swissup Themes', 'icon' => '🎨'],
            ['path' => 'app/code/Magento/', 'type' => 'Core Modules', 'icon' => '⚙️'],
            ['path' => 'app/design/frontend/Magento/', 'type' => 'Core Themes', 'icon' => '🎭'],
        ];

        $hasOverrides = false;
        foreach ($folderPaths as $folder) {
            if ($this->checkFolder($folder['path'], $output, $folder['icon'], $folder['type'])) {
                $hasOverrides = true;
            }
        }
        
        if (!$hasOverrides) {
            $output->writeln('<success>✅ No override folders detected - system integrity maintained</success>');
        }

        $this->displaySectionSeparator($output);
    }

    private function getCommandInfo(InputInterface $input, OutputInterface $output, $command, $description)
    {
        try {
            $response = $this->shell->execute($command);
            
            $output->writeln("<header>  $description</>");
            $lines = explode("\n", trim($response));
            foreach ($lines as $line) {
                if (trim($line)) {
                    $output->writeln("    <fg=white>│</> <comment>" . trim($line) . "</comment>");
                }
            }
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("    <fg=red>│</> <fg=red>❌ Error running \"$command\" command</>");
            return Cli::RETURN_FAILURE;
        }
    }

    private function isFolderEmpty($folderPath)
    {
        try {
            $items = scandir($folderPath);
            $items = array_diff($items, ['.', '..']);
            return empty($items);
        } catch (\Throwable $e) {
            throw new \Exception("Unable to check folder contents: " . $e->getMessage());
        }
    }

    private function checkFolder($folderPath, OutputInterface $output, $icon = '📁', $type = '')
    {
        try {
            if (!$this->isFolderEmpty($folderPath)) {
                $output->writeln("    <fg=red>│</> <warning>⚠️  $icon $type override detected: <fg=yellow>$folderPath</></warning>");
                return true;
            } else {
                $output->writeln("    <fg=green>│</> <success>✅ $icon $type: Clean</success>");
                return false;
            }
        } catch (\Exception $e) {
            $output->writeln("    <fg=cyan>│</> <fg=cyan>ℹ️  $icon $type: Not found (expected)</>");
            return false;
        }
    }

    private function outputMagentoThemeData(InputInterface $input, OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🎨 MAGENTO THEMES ANALYSIS');
        
        $themes = $this->themeCollectionFactory->create()->getData();

        if (empty($themes)) {
            $output->writeln('    <fg=cyan>│</> <comment>ℹ️  No themes found</comment>');
            $this->displaySectionSeparator($output);
            return;
        }

        $table = new Table($output);
        $table->setHeaders([
            '<header>ID</header>', 
            '<header>Parent ID</header>', 
            '<header>Theme Title</header>', 
            '<header>Type</header>',
            '<header>Status</header>'
        ]);
        $table->setStyle('box-double');
        $table->setColumnWidth(0, 4);
        $table->setColumnWidth(1, 11);
        $table->setColumnWidth(2, 25);
        $table->setColumnWidth(3, 6);
        $table->setColumnWidth(4, 13);

        $virtualThemesCount = 0;
        foreach ($themes as $theme) {
            $isVirtual = $theme['type'] == 1;
            if ($isVirtual) $virtualThemesCount++;
            
            $statusIcon = $isVirtual ? '❌ Virtual' : '✅ Physical';
            $style = $isVirtual ? 'fg=red' : 'fg=green';
            
            $table->addRow([
                "<$style>{$theme['theme_id']}</$style>",
                "<$style>" . ($theme['parent_id'] ?: 'N/A') . "</$style>",
                "<$style>{$theme['theme_title']}</$style>",
                "<$style>{$theme['type']}</$style>",
                "<$style>$statusIcon</$style>"
            ]);
        }

        $table->render();
        
        if ($virtualThemesCount > 0) {
            $output->writeln('');
            $output->writeln("    <warning>⚠️  Found $virtualThemesCount virtual theme(s) - use 'swissup:info:virtualfix' to fix</warning>");
        } else {
            $output->writeln('');
            $output->writeln('    <success>✅ All themes are properly configured</success>');
        }
        
        $this->displaySectionSeparator($output);
    }

    private function getDatabaseInfo()
    {
        try {
            $dbConfig = (array) $this->deploymentConfig->get('db/connection/default');
        } catch (\Throwable $exception) {
            $dbConfig = [];
        }

        return [
            'dbname' => $dbConfig['dbname'] ?? 'N/A',
            'username' => $dbConfig['username'] ?? 'N/A',
            'host' => $dbConfig['host'] ?? 'N/A',
            'password' => $dbConfig['password'] ?? 'N/A'
        ];
    }

    private function outputDatabaseInfo(OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🗄️ DATABASE CONFIGURATION');
        
        $dbInfo = $this->getDatabaseInfo();
        
        $dbData = [
            ['🏷️  Database Name', $dbInfo['dbname']],
            ['👤  Username', $dbInfo['username']],
            ['🌐  Host', $dbInfo['host']],
            ['🔑  Password', $dbInfo['password']]
        ];
        
        $table = new Table($output);
        $table->setHeaders(['<header>Property</header>', '<header>Value</header>']);
        $table->setStyle('box-double');
        $table->setColumnWidth(0, 20);
        $table->setColumnWidth(1, 25);
        
        foreach ($dbData as $row) {
            $table->addRow(["<fg=cyan>{$row[0]}</>", "<comment>{$row[1]}</comment>"]);
        }
        
        $table->render();

        $mysqlCommand = "mysql -h '{$dbInfo['host']}' --database='{$dbInfo['dbname']}' -u '{$dbInfo['username']}' -p";
        $output->writeln('');
        $output->writeln('    <header>💻 MySQL Connection Command:</header>');
        $output->writeln("    <fg=white>│</> <highlight>$mysqlCommand</highlight>");
        
        $this->displaySectionSeparator($output);
    }

    private function outputBackendUrl(OutputInterface $output)
    {
        $this->displaySectionHeader($output, '🔗 ADMIN ACCESS');
        
        $adminUrl = $this->backendUrl->getBaseUrl() . $this->backendUrl->getAreaFrontName();

        $output->writeln('    <header>🏪 Admin Panel URL:</header>');
        $output->writeln("    <fg=white>│</> <highlight>$adminUrl</highlight>");
        $output->writeln('');
        $output->writeln('    <fg=cyan>💡 Click the URL above to access your Magento admin panel</fg=cyan>');
        
        $this->displaySectionSeparator($output);
    }
}
