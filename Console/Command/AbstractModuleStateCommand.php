<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Magento\Framework\Module\Status;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Shared logic for the enable/disable module-state commands. Concrete commands
 * only declare which modules they manage, where the state is persisted and how
 * the group is labelled in the output.
 */
abstract class AbstractModuleStateCommand extends AbstractStyledCommand
{
    protected Status $moduleStatus;
    protected FullModuleList $fullModuleList;
    protected ModuleManager $moduleManager;

    public function __construct(
        Status $moduleStatus,
        FullModuleList $fullModuleList,
        ModuleManager $moduleManager
    ) {
        parent::__construct();
        $this->moduleStatus = $moduleStatus;
        $this->fullModuleList = $fullModuleList;
        $this->moduleManager = $moduleManager;
    }

    /**
     * Whether the given module is managed by this command.
     */
    abstract protected function isManagedModule(string $moduleName): bool;

    /**
     * Path (relative to BP) of the file used to preserve module state.
     */
    abstract protected function getStateFile(): string;

    /**
     * Human-readable label for the managed group (e.g. "Swissup", "3rd-party").
     */
    abstract protected function getScopeLabel(): string;

    /**
     * All managed module names, sorted alphabetically.
     *
     * @return string[]
     */
    protected function getManagedModules(): array
    {
        $modules = [];
        foreach ($this->fullModuleList->getNames() as $moduleName) {
            if ($this->isManagedModule($moduleName)) {
                $modules[] = $moduleName;
            }
        }

        sort($modules);
        return $modules;
    }

    /**
     * @param string[] $modules
     * @return string[]
     */
    protected function getEnabledModules(array $modules): array
    {
        $enabled = [];
        foreach ($modules as $moduleName) {
            if ($this->moduleManager->isEnabled($moduleName)) {
                $enabled[] = $moduleName;
            }
        }

        return $enabled;
    }

    /**
     * @param string[] $enabledModules
     */
    protected function saveModulesState(array $enabledModules, OutputInterface $output): void
    {
        $this->displaySectionHeader($output, '💾 SAVING CURRENT STATE');

        $stateFile = BP . '/' . $this->getStateFile();
        $stateData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'enabled_modules' => $enabledModules
        ];

        $dir = dirname($stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($stateFile, json_encode($stateData, JSON_PRETTY_PRINT))) {
            $output->writeln("    <fg=white>│</> <success>✅ State saved to: " . $this->getStateFile() . "</success>");
            $output->writeln("    <fg=white>│</> <comment>📊 Enabled modules count: " . count($enabledModules) . "</comment>");
        } else {
            throw new \Exception("Failed to save state file: $stateFile");
        }

        $this->displaySectionSeparator($output);
    }

    protected function loadModulesState(OutputInterface $output): array
    {
        $this->displaySectionHeader($output, '📂 LOADING SAVED STATE');

        $stateFile = BP . '/' . $this->getStateFile();
        $stateContent = file_get_contents($stateFile);
        $savedState = json_decode($stateContent, true);

        if (!$savedState || !isset($savedState['enabled_modules'])) {
            throw new \Exception("Invalid state file format");
        }

        $output->writeln("    <fg=white>│</> <success>✅ State loaded from: " . $this->getStateFile() . "</success>");
        $output->writeln("    <fg=white>│</> <comment>📅 Saved on: " . ($savedState['timestamp'] ?? 'Unknown') . "</comment>");
        $output->writeln("    <fg=white>│</> <comment>📊 Modules to enable: " . count($savedState['enabled_modules']) . "</comment>");

        $this->displaySectionSeparator($output);

        return $savedState;
    }

    protected function removeStateFile(OutputInterface $output): void
    {
        $this->displaySectionHeader($output, '🧹 CLEANUP');

        $stateFile = BP . '/' . $this->getStateFile();
        if (unlink($stateFile)) {
            $output->writeln("    <fg=white>│</> <success>✅ State file removed: " . $this->getStateFile() . "</success>");
        } else {
            $output->writeln("    <fg=white>│</> <warning>⚠️  Could not remove state file: " . $this->getStateFile() . "</warning>");
        }

        $this->displaySectionSeparator($output);
    }

    /**
     * Enable or disable the given modules and report progress.
     *
     * @param string[] $modules
     */
    protected function applyModulesState(bool $enable, array $modules, OutputInterface $output): void
    {
        $this->displaySectionHeader($output, $enable ? '🔄 ENABLING MODULES' : '🔄 DISABLING MODULES');

        $verb = $enable ? 'Enabling' : 'Disabling';
        $icon = $enable ? '<fg=green>✅</>' : '<fg=red>❌</>';

        $output->writeln("    <fg=white>│</> <comment>$verb " . count($modules) . " module(s)...</comment>");
        $output->writeln('');

        try {
            $this->moduleStatus->setIsEnabled($enable, $modules);

            foreach ($modules as $moduleName) {
                $output->writeln("    <fg=white>│</> $icon <comment>$moduleName</comment>");
            }

            $output->writeln('');
            $output->writeln("    <fg=white>│</> <success>✅ All modules " . ($enable ? 'enabled' : 'disabled') . " successfully</success>");
        } catch (\Exception $e) {
            throw new \Exception("Failed to " . ($enable ? 'enable' : 'disable') . " modules: " . $e->getMessage());
        }

        $this->displaySectionSeparator($output);
    }

    protected function confirmAction(InputInterface $input, OutputInterface $output, string $action, int $moduleCount): bool
    {
        if ($input->getOption('no-interaction')) {
            return true;
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "<fg=yellow>⚠️  Are you sure you want to $action $moduleCount " . $this->getScopeLabel() . " module(s)? [y/N] </>",
            false
        );

        return $helper->ask($input, $output, $question);
    }
}
