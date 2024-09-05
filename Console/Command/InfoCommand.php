<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\State;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class InfoCommand extends Command
{
    private $appState;

    public function __construct(State $appState)
    {
        parent::__construct();
        $this->appState = $appState;
    }

    protected function configure()
    {
        $this->setName('swissup:info')
             ->setDescription('Store environment information');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->outputEnvironmentInfo($input, $output);
            $this->checkClientOverrides($output);
            $this->outputDatabaseInfo($output);
            $this->outputBackendUrl($output);
            $this->outputMagentoThemeData($input, $output);

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return Cli::RETURN_FAILURE;
        }
    }

    private function outputEnvironmentInfo(InputInterface $input, OutputInterface $output)
    {
        $commands = [
            'php_version' => 'php -v | head -n 1 && whereis php',
            'magento_version' => 'php bin/magento --version',
            'composer_version' => 'composer --version && whereis composer',
            'nginx_user' => 'whoami',
        ];

        foreach ($commands as $key => $command) {
            $this->getCommandInfo($input, $output, $command, ucfirst(str_replace('_', ' ', $key)) . ':');
        }
    }

    private function checkClientOverrides(OutputInterface $output)
    {
        $folderPaths = [
            'app/code/Swissup/',
            'app/design/frontend/Swissup/',
            'app/code/Magento/',
            'app/design/frontend/Magento/',
        ];

        foreach ($folderPaths as $folderPath) {
            $this->checkFolder($folderPath, $output);
        }

        $output->writeln('_____________________________');
    }

    private function getCommandInfo(InputInterface $input, OutputInterface $output, $command, $description)
    {
        try {
            $result = [];
            $shellExecute = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Shell::class);
            $response = $shellExecute->execute($command, $result);
            
            $output->writeln("<info>$description</info>");
            $output->writeln("<comment>$response</comment>");
            $output->writeln('_____________________________');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error running \"$command\" command</error>");
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

    private function checkFolder($folderPath, OutputInterface $output)
    {
        try {
            if (!$this->isFolderEmpty($folderPath)) {
                $output->writeln("<error>The folder \"$folderPath\" is not empty.</error>");
            }
        } catch (\Exception $e) {
            // $output->writeln("<comment>Skipped checking folder \"$folderPath\": " . $e->getMessage() . "</comment>");
        }
    }

    private function outputMagentoThemeData(InputInterface $input, OutputInterface $output)
    {
        $this->initMagento();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $themeCollection = $objectManager->create(\Magento\Theme\Model\ResourceModel\Theme\Collection::class);
        $themes = $themeCollection->getData();

        $output->writeln('<info>Magento 2 Theme Table Data:</info>');

        if (empty($themes)) {
            $output->writeln('<comment>No themes found</comment>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Parent ID', 'Theme Title', 'Type']);

        foreach ($themes as $theme) {
            $style = $theme['type'] == 1 ? 'error' : 'info';
            $table->addRow([
                "<$style>{$theme['theme_id']}</$style>",
                "<$style>{$theme['parent_id']}</$style>",
                "<$style>{$theme['theme_title']}</$style>",
                "<$style>{$theme['type']}</$style>"
            ]);
        }

        $table->render();
    }

    private function initMagento()
    {
        try {
            $bootstrap = Bootstrap::create(BP, $_SERVER);
            $objectManager = $bootstrap->getObjectManager();
            $objectManager->get(State::class)->setAreaCode('frontend');
        } catch (LocalizedException | NoSuchEntityException $e) {
            throw new \Exception("Unable to initialize Magento: " . $e->getMessage());
        }
    }

    private function getDatabaseInfo()
    {
        $envFile = BP . '/app/etc/env.php';
        if (file_exists($envFile)) {
            $env = include $envFile;
            if (isset($env['db']['connection']['default'])) {
                $dbConfig = $env['db']['connection']['default'];
                return [
                    'dbname' => $dbConfig['dbname'] ?? 'N/A',
                    'username' => $dbConfig['username'] ?? 'N/A',
                    'host' => $dbConfig['host'] ?? 'N/A',
                    'password' => $dbConfig['password'] ?? 'N/A'
                ];
            }
        }
        return ['dbname' => 'N/A', 'username' => 'N/A', 'host' => 'N/A', 'password' => 'N/A'];
    }

    private function outputDatabaseInfo(OutputInterface $output)
    {
        $dbInfo = $this->getDatabaseInfo();
        $output->writeln('<info>Database Information:</info>');
        $output->writeln("<comment>Database Name: {$dbInfo['dbname']}</comment>");
        $output->writeln("<comment>Database User: {$dbInfo['username']}</comment>");
        $output->writeln("<comment>Database Host: {$dbInfo['host']}</comment>");
        $output->writeln("<comment>Database Password: {$dbInfo['password']}</comment>");

        $mysqlCommand = "mysql -h '{$dbInfo['host']}' --database='{$dbInfo['dbname']}' -u '{$dbInfo['username']}' -p";
        $output->writeln("\n<info>MySQL Connection Command:</info>");
        $output->writeln("<comment>$mysqlCommand</comment>");
        $output->writeln('_____________________________');
    }

    private function outputBackendUrl(OutputInterface $output)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $backendUrl = $objectManager->get(\Magento\Backend\Model\UrlInterface::class);
        $adminUrl = $backendUrl->getBaseUrl() . $backendUrl->getAreaFrontName();

        $output->writeln('<info>Backend URL:</info>');
        $output->writeln("<comment>$adminUrl</comment>");
        $output->writeln('_____________________________');
    }
}
