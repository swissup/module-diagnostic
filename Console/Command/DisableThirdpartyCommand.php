<?php

namespace Swissup\Diagnostic\Console\Command;

class DisableThirdpartyCommand extends AbstractDisableModulesCommand
{
    const STATE_FILE = 'var/thirdparty_modules_state.json';

    protected function configure()
    {
        $this->setName('swissup:info:disable-thirdparty')
             ->setDescription('Disable all currently enabled 3rd-party modules (except Swissup_ and Magento_)');
    }

    protected function isManagedModule(string $moduleName): bool
    {
        return strpos($moduleName, 'Magento_') !== 0 && strpos($moduleName, 'Swissup_') !== 0;
    }

    protected function getStateFile(): string
    {
        return self::STATE_FILE;
    }

    protected function getScopeLabel(): string
    {
        return '3rd-party';
    }

    protected function getEnableCommandName(): string
    {
        return 'swissup:info:enable-thirdparty';
    }
}
