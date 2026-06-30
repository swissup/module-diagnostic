<?php

namespace Swissup\Diagnostic\Console\Command;

class DisableSwissupCommand extends AbstractDisableModulesCommand
{
    const STATE_FILE = 'var/swissup_modules_state.json';

    protected function configure()
    {
        $this->setName('swissup:info:disable-swissup')
             ->setDescription('Disable all currently enabled Swissup modules');
    }

    protected function isManagedModule(string $moduleName): bool
    {
        return strpos($moduleName, 'Swissup_') === 0;
    }

    protected function getStateFile(): string
    {
        return self::STATE_FILE;
    }

    protected function getScopeLabel(): string
    {
        return 'Swissup';
    }

    protected function getEnableCommandName(): string
    {
        return 'swissup:info:enable-swissup';
    }
}
