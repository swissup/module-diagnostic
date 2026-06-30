<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Store\Model\ScopeInterface;

class AssetsCommand extends AbstractStyledCommand
{
    private WriterInterface $configWriter;
    private ScopeConfigInterface $scopeConfig;
    private TypeListInterface $cacheTypeList;

    const MERGE_CSS = 'dev/css/merge_css_files';
    const MINIFY_CSS = 'dev/css/minify_files';
    const MERGE_JS = 'dev/js/merge_files';
    const MINIFY_JS = 'dev/js/minify_files';
    const ENABLE_JS_BUNDLING = 'dev/js/enable_js_bundling';
    const MINIFY_HTML = 'dev/template/minify_html';

    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList
    ) {
        parent::__construct();
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
    }

    protected function configure()
    {
        $this->setName('swissup:info:assets')
             ->setDescription('Manage JS/CSS merge and minification settings')
             ->addOption(
                 'merge-css',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable CSS merge (1 or 0)'
             )
             ->addOption(
                 'minify-css',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable CSS minification (1 or 0)'
             )
             ->addOption(
                 'merge-js',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable JS merge (1 or 0)'
             )
             ->addOption(
                 'minify-js',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable JS minification (1 or 0)'
             )
             ->addOption(
                 'bundle-js',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable JS bundling (1 or 0)'
             )
             ->addOption(
                 'minify-html',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'Enable/Disable HTML minification (1 or 0)'
             )
             ->addOption(
                 'enable-all',
                 null,
                 InputOption::VALUE_NONE,
                 'Enable all optimization settings'
             )
             ->addOption(
                 'disable-all',
                 null,
                 InputOption::VALUE_NONE,
                 'Disable all optimization settings'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeCustomStyles($output);
        $this->displayWelcomeBanner($output, '⚡ ASSETS OPTIMIZATION MANAGER', 'JS/CSS Merge & Minification Control');

        try {
            $hasChanges = false;

            // Handle enable-all flag
            if ($input->getOption('enable-all')) {
                $this->setAllSettings(1, $output);
                $hasChanges = true;
            }
            // Handle disable-all flag
            elseif ($input->getOption('disable-all')) {
                $this->setAllSettings(0, $output);
                $hasChanges = true;
            }
            // Handle individual settings
            else {
                $hasChanges = $this->processIndividualSettings($input, $output);
            }

            // Display current status
            $this->displayCurrentStatus($output);

            if ($hasChanges) {
                $this->cleanCache($output);
                $this->displaySuccessBanner($output, 'Configuration updated successfully!');
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ Error: " . $e->getMessage() . "</>");
            return Cli::RETURN_FAILURE;
        }
    }

    private function processIndividualSettings(InputInterface $input, OutputInterface $output): bool
    {
        $hasChanges = false;
        $settings = [
            'merge-css' => [self::MERGE_CSS, 'CSS Merge'],
            'minify-css' => [self::MINIFY_CSS, 'CSS Minification'],
            'merge-js' => [self::MERGE_JS, 'JS Merge'],
            'minify-js' => [self::MINIFY_JS, 'JS Minification'],
            'bundle-js' => [self::ENABLE_JS_BUNDLING, 'JS Bundling'],
            'minify-html' => [self::MINIFY_HTML, 'HTML Minification'],
        ];

        $output->writeln('<header>╭─── 🔧 UPDATING SETTINGS ───╮</header>');
        $output->writeln('');

        foreach ($settings as $option => $data) {
            $value = $input->getOption($option);
            if ($value !== null) {
                $this->updateSetting($data[0], $value, $data[1], $output);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
            $output->writeln('');
        }

        return $hasChanges;
    }

    private function setAllSettings(int $value, OutputInterface $output)
    {
        $action = $value ? 'ENABLING' : 'DISABLING';
        $output->writeln("<header>╭─── 🔧 $action ALL OPTIMIZATIONS ───╮</header>");
        $output->writeln('');

        $settings = [
            [self::MERGE_CSS, 'CSS Merge'],
            [self::MINIFY_CSS, 'CSS Minification'],
            [self::MERGE_JS, 'JS Merge'],
            [self::MINIFY_JS, 'JS Minification'],
            [self::ENABLE_JS_BUNDLING, 'JS Bundling'],
            [self::MINIFY_HTML, 'HTML Minification'],
        ];

        foreach ($settings as $setting) {
            $this->updateSetting($setting[0], $value, $setting[1], $output);
        }

        $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
        $output->writeln('');
    }

    private function updateSetting(string $path, $value, string $label, OutputInterface $output)
    {
        $value = (int) $value;
        $this->configWriter->save($path, $value);
        
        $status = $value ? '✅ Enabled' : '❌ Disabled';
        $color = $value ? 'green' : 'red';
        
        $output->writeln("    <fg=white>│</> <fg=$color>$status</> <comment>$label</comment>");
    }

    private function displayCurrentStatus(OutputInterface $output)
    {
        $output->writeln('<header>╭─── 📊 CURRENT CONFIGURATION ───╮</header>');
        $output->writeln('');

        $settings = [
            ['path' => self::MERGE_CSS, 'label' => 'CSS Merge'],
            ['path' => self::MINIFY_CSS, 'label' => 'CSS Minification'],
            ['path' => self::MERGE_JS, 'label' => 'JS Merge'],
            ['path' => self::MINIFY_JS, 'label' => 'JS Minification'],
            ['path' => self::ENABLE_JS_BUNDLING, 'label' => 'JS Bundling'],
            ['path' => self::MINIFY_HTML, 'label' => 'HTML Minification'],
        ];

        $table = $this->createTable($output);
        $table->setHeaders(['<header>Setting</header>', '<header>Status</header>', '<header>Value</header>']);
        $table->setColumnWidth(0, 25);
        $table->setColumnWidth(1, 12);
        $this->rightAlignColumns($table, 2);

        foreach ($settings as $setting) {
            $value = $this->scopeConfig->getValue($setting['path'], ScopeInterface::SCOPE_STORE);
            $enabled = (bool) $value;
            $status = $enabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';
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

    private function displayUsageExamples(OutputInterface $output)
    {
        $output->writeln('<header>💡 Usage Examples:</header>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Enable all optimizations:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:assets --enable-all</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Disable all optimizations:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:assets --disable-all</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Enable CSS merge only:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:assets --merge-css=1</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Disable JS minification:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:assets --minify-js=0</highlight>');
        $output->writeln('');
        $output->writeln('    <fg=cyan>│</> Multiple settings at once:');
        $output->writeln('    <fg=white>│</> <highlight>bin/magento swissup:info:assets --merge-css=1 --minify-css=1 --merge-js=0</highlight>');
        $output->writeln('');
        $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
    }

    private function cleanCache(OutputInterface $output)
    {
        $output->writeln('<header>╭─── 🧹 CLEANING CACHE ───╮</header>');
        $output->writeln('');
        
        $types = ['config', 'full_page', 'block_html', 'layout'];
        
        foreach ($types as $type) {
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
