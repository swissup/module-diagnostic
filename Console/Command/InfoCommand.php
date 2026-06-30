<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Shell;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;

class InfoCommand extends AbstractStyledCommand
{
    private Shell $shell;
    private DeploymentConfig $deploymentConfig;
    private ProductMetadataInterface $productMetadata;
    private BackendUrlInterface $backendUrl;
    private ThemeCollectionFactory $themeCollectionFactory;

    public function __construct(
        Shell $shell,
        DeploymentConfig $deploymentConfig,
        ProductMetadataInterface $productMetadata,
        BackendUrlInterface $backendUrl,
        ThemeCollectionFactory $themeCollectionFactory
    ) {
        parent::__construct();
        $this->shell = $shell;
        $this->deploymentConfig = $deploymentConfig;
        $this->productMetadata = $productMetadata;
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

        // PHP version and binary path - read in-process (portable, no shell needed)
        $this->displayInfo($output, '🐘 PHP Version', [
            'PHP ' . PHP_VERSION,
            'Binary: ' . PHP_BINARY,
        ]);

        // Magento version - read from framework metadata instead of spawning a
        // second `bin/magento` bootstrap (slow and fragile under CLI)
        $this->displayInfo($output, '🛍️ Magento Version', [
            $this->productMetadata->getName() . ' '
                . $this->productMetadata->getEdition() . ', version '
                . $this->productMetadata->getVersion(),
        ]);

        // Composer is a genuinely external tool - still queried via shell
        $this->getCommandInfo($input, $output, 'composer --version', '📦 Composer Version');

        // Current system user - read in-process (portable, no shell needed)
        $this->displayInfo($output, '👤 System User', [$this->getSystemUser()]);

        $this->displaySectionSeparator($output);
    }

    private function getSystemUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (!empty($info['name'])) {
                return $info['name'];
            }
        }

        return get_current_user() ?: 'unknown';
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
            $this->displayInfo($output, $description, explode("\n", trim($response)));

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("    <fg=red>│</> <fg=red>❌ Error running \"$command\" command</>");
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Render a titled info block with one indented line per entry.
     *
     * @param OutputInterface $output
     * @param string $description
     * @param string[] $lines
     * @return void
     */
    private function displayInfo(OutputInterface $output, string $description, array $lines): void
    {
        $output->writeln("<header>  $description</>");
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $output->writeln("    <fg=white>│</> <comment>$line</comment>");
            }
        }
        $output->writeln('');
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

        $table = $this->createTable($output);
        $table->setHeaders([
            '<header>ID</header>',
            '<header>Parent ID</header>',
            '<header>Theme Title</header>',
            '<header>Type</header>',
            '<header>Status</header>'
        ]);
        $table->setColumnWidth(2, 25);
        $this->rightAlignColumns($table, 0, 1, 3);

        $virtualThemesCount = 0;
        foreach ($themes as $theme) {
            $isVirtual = $theme['type'] == 1;
            if ($isVirtual) $virtualThemesCount++;

            $status = $isVirtual ? 'Virtual' : 'Physical';
            $style = $isVirtual ? 'fg=red' : 'fg=green';

            $table->addRow([
                "<$style>{$theme['theme_id']}</$style>",
                "<$style>" . ($theme['parent_id'] ?: 'N/A') . "</$style>",
                "<$style>{$theme['theme_title']}</$style>",
                "<$style>{$theme['type']}</$style>",
                "<$style>$status</$style>"
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
            ['Database Name', $dbInfo['dbname']],
            ['Username', $dbInfo['username']],
            ['Host', $dbInfo['host']],
            ['Password', $dbInfo['password']]
        ];

        $table = $this->createTable($output);
        $table->setHeaders(['<header>Property</header>', '<header>Value</header>']);
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
