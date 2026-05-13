<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class HintsCommand extends AbstractStyledCommand
{
    private WriterInterface $configWriter;
    private ScopeConfigInterface $scopeConfig;
    private TypeListInterface $cacheTypeList;
    private StoreManagerInterface $storeManager;

    const HINTS_STOREFRONT = 'dev/debug/template_hints_storefront';
    const HINTS_ADMIN      = 'dev/debug/template_hints_admin';
    const HINTS_BLOCKS     = 'dev/debug/template_hints_blocks';

    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct();
        $this->configWriter = $configWriter;
        $this->scopeConfig  = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
        $this->storeManager = $storeManager;
    }

    protected function configure()
    {
        $this->setName('swissup:info:hints')
             ->setDescription('Enable or disable Magento template path hints')
             ->addOption(
                 'storefront',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable storefront hints (1 or 0)'
             )
             ->addOption(
                 'admin',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable admin hints (1 or 0)'
             )
             ->addOption(
                 'blocks',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable block name hints (1 or 0)'
             )
             ->addOption(
                 'enable-all',
                 null,
                 InputOption::VALUE_NONE,
                 'Enable all template path hints'
             )
             ->addOption(
                 'disable-all',
                 null,
                 InputOption::VALUE_NONE,
                 'Disable all template path hints'
             )
             ->addOption(
                 'store',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Store ID or code to apply hints for (default: applies to default scope)',
                 null
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeCustomStyles($output);
        $this->displayWelcomeBanner($output, '🔍 TEMPLATE PATH HINTS', 'Enable / Disable Magento Path Hints');

        try {
            [$scope, $scopeId] = $this->resolveScope($input, $output);
            $hasChanges = false;

            if ($input->getOption('enable-all')) {
                $this->setAllHints(1, $scope, $scopeId, $output);
                $hasChanges = true;
            } elseif ($input->getOption('disable-all')) {
                $this->setAllHints(0, $scope, $scopeId, $output);
                $hasChanges = true;
            } else {
                $hasChanges = $this->processIndividualOptions($input, $scope, $scopeId, $output);
            }

            $this->displayCurrentStatus($scope, $scopeId, $output);

            if ($hasChanges) {
                $this->cleanCache($output);
                $this->displaySuccessBanner($output, 'Template hints configuration updated successfully!');
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ Error: " . $e->getMessage() . "</>");
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Resolve the config scope and scopeId from the --store option.
     * Returns [scope string, scope ID].
     */
    private function resolveScope(InputInterface $input, OutputInterface $output): array
    {
        $storeOption = $input->getOption('store');

        if ($storeOption === null) {
            return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null];
        }

        try {
            $store = $this->storeManager->getStore($storeOption);
            $output->writeln(
                "    <fg=cyan>│</> <comment>Applying to store: <fg=white>{$store->getName()}</> (ID: {$store->getId()})</comment>"
            );
            $output->writeln('');
            return [ScopeInterface::SCOPE_STORES, $store->getId()];
        } catch (\Exception $e) {
            throw new \Exception("Store \"{$storeOption}\" not found.");
        }
    }

    private function processIndividualOptions(
        InputInterface $input,
        string $scope,
        $scopeId,
        OutputInterface $output
    ): bool {
        $options = [
            'storefront' => [self::HINTS_STOREFRONT, 'Storefront Hints'],
            'admin'      => [self::HINTS_ADMIN,      'Admin Hints'],
            'blocks'     => [self::HINTS_BLOCKS,     'Block Name Hints'],
        ];

        $hasChanges = false;
        $output->writeln('<header>╭─── 🔧 UPDATING SETTINGS ───╮</header>');
        $output->writeln('');

        foreach ($options as $optionName => [$path, $label]) {
            $value = $input->getOption($optionName);
            if ($value !== null) {
                $this->updateSetting($path, $value, $label, $scope, $scopeId, $output);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
            $output->writeln('');
        }

        return $hasChanges;
    }

    private function setAllHints(int $value, string $scope, $scopeId, OutputInterface $output): void
    {
        $action = $value ? 'ENABLING' : 'DISABLING';
        $output->writeln("<header>╭─── 🔧 $action ALL HINTS ───╮</header>");
        $output->writeln('');

        $settings = [
            [self::HINTS_STOREFRONT, 'Storefront Hints'],
            [self::HINTS_ADMIN,      'Admin Hints'],
            [self::HINTS_BLOCKS,     'Block Name Hints'],
        ];

        foreach ($settings as [$path, $label]) {
            $this->updateSetting($path, $value, $label, $scope, $scopeId, $output);
        }

        $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
        $output->writeln('');
    }

    private function updateSetting(
        string $path,
        $value,
        string $label,
        string $scope,
        $scopeId,
        OutputInterface $output
    ): void {
        $value = (int) $value;

        if ($scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            $this->configWriter->save($path, $value);
        } else {
            $this->configWriter->save($path, $value, $scope, (int) $scopeId);
        }

        $status = $value ? '✅ Enabled' : '❌ Disabled';
        $color  = $value ? 'green' : 'red';
        $output->writeln("    <fg=white>│</> <fg=$color>$status</> <comment>$label</comment>");
    }

    private function displayCurrentStatus(string $scope, $scopeId, OutputInterface $output): void
    {
        $output->writeln('<header>╭─── 📊 CURRENT CONFIGURATION ───╮</header>');
        $output->writeln('');

        $settings = [
            ['path' => self::HINTS_STOREFRONT, 'label' => '🖥️  Storefront Hints'],
            ['path' => self::HINTS_ADMIN,      'label' => '⚙️  Admin Hints'],
            ['path' => self::HINTS_BLOCKS,     'label' => '🧩 Block Name Hints'],
        ];

        $table = new Table($output);
        $table->setHeaders(['<header>Setting</header>', '<header>Status</header>', '<header>Value</header>']);
        $table->setStyle('box-double');
        $table->setColumnWidth(0, 25);
        $table->setColumnWidth(1, 15);
        $table->setColumnWidth(2, 7);

        foreach ($settings as $setting) {
            if ($scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
                $value = $this->scopeConfig->getValue($setting['path']);
            } else {
                $value = $this->scopeConfig->getValue($setting['path'], $scope, $scopeId);
            }

            $enabled      = (bool) $value;
            $status       = $enabled ? '<fg=green>✅ Enabled</>'  : '<fg=red>❌ Disabled</>';
            $valueDisplay = $enabled ? '<fg=green>1</>' : '<fg=red>0</>';

            $table->addRow([
                "<comment>{$setting['label']}</comment>",
                $status,
                $valueDisplay
            ]);
        }

        $table->render();
        $output->writeln('');

        $this->displayUsageExamples($output);
    }

    private function displayUsageExamples(OutputInterface $output): void
    {
        $output->writeln('<header>💡 Usage Examples:</header>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Enable all hints:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:hints --enable-all</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Disable all hints:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:hints --disable-all</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Enable storefront hints only:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:hints --storefront=1</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Enable storefront + block hints for a specific store:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:hints --storefront=1 --blocks=1 --store=1</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Disable admin hints:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:hints --admin=0</highlight>');
        $output->writeln('');
        $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
    }

    private function cleanCache(OutputInterface $output): void
    {
        $output->writeln('<header>╭─── 🧹 CLEANING CACHE ───╮</header>');
        $output->writeln('');

        foreach (['config', 'full_page', 'block_html', 'layout'] as $type) {
            try {
                $this->cacheTypeList->cleanType($type);
                $output->writeln("    <fg=white>│</> <success>✅ Cleaned: $type</success>");
            } catch (\Exception $e) {
                $output->writeln("    <fg=white>│</> <warning>⚠️  Could not clean: $type</warning>");
            }
        }

        $output->writeln('');
        $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
    }
}
