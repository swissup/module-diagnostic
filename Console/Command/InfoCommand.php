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
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\State $appState
     */
    public function __construct(State $appState)
    {
        parent::__construct();
        $this->appState = $appState;
    }

    /**
     * Configures the command with a name and description.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('swissup:info')
             ->setDescription('Store environment information');
    }

    /**
     * Executes the Swissup info command.
     *
     * This function is responsible for gathering and displaying store environment information.
     * It checks the environment using a set of predefined commands and folder paths.
     * It also outputs Magento 2 theme table data.
     *
     * @param InputInterface $input The input interface instance.
     * @param OutputInterface $output The output interface instance.
     *
     * @throws \Exception If an error occurs during execution.
     *
     * @return int Cli::RETURN_SUCCESS if execution is successful, Cli::RETURN_FAILURE otherwise.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commands = [
            'php_version' => 'php -v | head -n 1 && whereis php',
            'magento_version' => 'php bin/magento --version',
            'composer_version' => 'composer --version && whereis composer',
            'nginx_user' => 'whoami',
        ];

        $folderPaths = [
            'app/code/Swissup/',
            'app/design/frontend/Swissup/',
            'app/code/Magento/',
            'app/design/frontend/Magento/',
        ];

        try {
            // Environment Check commands

            foreach ($commands as $key => $value) {
                $this->getCommandInfo($input, $output, $value, ucfirst(str_replace('_', ' ', $key)) . ':');
            }

            // Clients Overrides check

            foreach ($folderPaths as $folderPath) {
                $this->checkFolder($folderPath, $output);
            }

            $output->writeln('_____________________________');

            // Output Magento 2 "theme" table data
            $this->outputMagentoThemeData($input, $output);

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Executes a given command and outputs the result to the console.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @param string $command The command to execute.
     * @param string $description The description of the command.
     * @return int The return value indicating the success or failure of the command.
     */
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

    /**
     * Checks if a given folder is empty.
     *
     * @param string $folderPath The path to the folder to check.
     * @throws \Exception If an error occurs while checking the folder contents.
     * @return bool True if the folder is empty, false otherwise.
     */
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

    /**
     * Checks if a given folder is empty and outputs an error message if it's not.
     *
     * @param string $folderPath The path to the folder to be checked.
     * @param OutputInterface $output The output interface to write the error message.
     * @throws \Exception If an error occurs while checking the folder.
     * @return void
     */
    private function checkFolder($folderPath, OutputInterface $output)
    {
        try {
            if (!$this->isFolderEmpty($folderPath)) {
                $output->writeln("<error>The folder \"$folderPath\" is not empty.</error>");
            }
        } catch (\Exception $e) {
            // Log or output a message about the skipped folder
            // $output->writeln("<comment>Skipped checking folder \"$folderPath\": " . $e->getMessage() . "</comment>");
        }
    }

    /**
     * Outputs the Magento 2 "theme" table data to the console.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @throws \Exception If an error occurs while retrieving the theme data.
     * @return void
     */
    private function outputMagentoThemeData(InputInterface $input, OutputInterface $output)
    {
        $this->initMagento();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $themeCollection = $objectManager->create(\Magento\Theme\Model\ResourceModel\Theme\Collection::class);
        $themes = $themeCollection->getData();

        $io = new SymfonyStyle($input, $output);

        $output->writeln('<info>Magento 2 Theme Table Data:</info>');

        if (empty($themes)) {
            $output->writeln('<comment>No themes found</comment>');
            return;
        }

        // Create a table
        $table = new Table($output);
        $table->setHeaders(['ID', 'Parent ID', 'Theme Title', 'Type']);

        foreach ($themes as $theme) {
            // Determine the style based on the "Type" value
            $style = $theme['type'] == 1 ? 'error' : 'info';

            // Add a row to the table with the specified style
            $table->addRow([
                "<$style>{$theme['theme_id']}</$style>",
                "<$style>{$theme['parent_id']}</$style>",
                "<$style>{$theme['theme_title']}</$style>",
                "<$style>{$theme['type']}</$style>"
            ]);
        }

        // Render the table
        $table->render();
    }

    /**
     * Initializes the Magento environment by bootstrapping and setting the area code.
     *
     * @throws \Exception If an error occurs during initialization.
     * @return void
     */
    private function initMagento()
    {
        // Bootstrap Magento to initialize the environment
        try {
            $bootstrap = Bootstrap::create(BP, $_SERVER);
            $objectManager = $bootstrap->getObjectManager();
            $objectManager->get(State::class)->setAreaCode('frontend');
        } catch (LocalizedException | NoSuchEntityException $e) {
            throw new \Exception("Unable to initialize Magento: " . $e->getMessage());
        }
    }
}
