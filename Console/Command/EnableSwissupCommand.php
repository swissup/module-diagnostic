<?php

namespace Swissup\Diagnostic\Console\Command;

class EnableSwissupCommand extends AbstractEnableModulesCommand
{
    const STATE_FILE = 'var/swissup_modules_state.json';

    protected function configure()
    {
        $this->setName('swissup:info:enable-swissup')
             ->setDescription('Enable previously disabled Swissup modules');
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

    protected function getDisableCommandName(): string
    {
        return 'swissup:info:disable-swissup';
    }
}
