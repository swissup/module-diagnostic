<?php

namespace Swissup\Diagnostic\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

abstract class AbstractStyledCommand extends Command
{
    /**
     * Initialize custom output styles for consistent formatting
     *
     * @param OutputInterface $output
     * @return void
     */
    protected function initializeCustomStyles(OutputInterface $output): void
    {
        $outputFormatter = $output->getFormatter();
        $outputFormatter->setStyle('header', new OutputFormatterStyle('cyan', null, ['bold']));
        $outputFormatter->setStyle('success', new OutputFormatterStyle('green', null, ['bold']));
        $outputFormatter->setStyle('warning', new OutputFormatterStyle('yellow', null, ['bold']));
        $outputFormatter->setStyle('highlight', new OutputFormatterStyle('white', 'blue', ['bold']));
    }

    /**
     * Display a welcome banner with title and subtitle
     *
     * @param OutputInterface $output
     * @param string $title
     * @param string $subtitle
     * @return void
     */
    protected function displayWelcomeBanner(OutputInterface $output, string $title, string $subtitle = ''): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>┌─────────────────────────────────────────────────────────────┐</>');
        $output->writeln('<fg=cyan>│</> <fg=white;bg=blue>' . $this->centerText($title, 53) . '</> <fg=cyan>│</>');
        if ($subtitle) {
            $output->writeln('<fg=cyan>│</> <fg=white;bg=blue>' . $this->centerText($subtitle, 53) . '</> <fg=cyan>│</>');
        }
        $output->writeln('<fg=cyan>└─────────────────────────────────────────────────────────────┘</>');
        $output->writeln('');
    }

    /**
     * Display a completion/success banner
     *
     * @param OutputInterface $output
     * @param string $message
     * @return void
     */
    protected function displaySuccessBanner(OutputInterface $output, string $message = 'Operation completed successfully!'): void
    {
        $output->writeln('');
        $output->writeln("<fg=green>✅ $message</>");
        $output->writeln('<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $output->writeln('');
    }

    /**
     * Display a section header
     *
     * @param OutputInterface $output
     * @param string $title
     * @return void
     */
    protected function displaySectionHeader(OutputInterface $output, string $title): void
    {
        $output->writeln('');
        $output->writeln("<header>╭─── $title ───╮</>");
        $output->writeln('');
    }

    /**
     * Display a section separator
     *
     * @param OutputInterface $output
     * @return void
     */
    protected function displaySectionSeparator(OutputInterface $output): void
    {
        $output->writeln('<fg=cyan>─────────────────────────────────────────────────────────────</>');
        $output->writeln('');
    }

    /**
     * Center text within a given width
     *
     * @param string $text
     * @param int $width
     * @return string
     */
    private function centerText(string $text, int $width): string
    {
        $textLength = mb_strlen($text);
        if ($textLength >= $width) {
            return $text;
        }
        
        $padding = ($width - $textLength) / 2;
        $leftPadding = str_repeat(' ', (int) floor($padding));
        $rightPadding = str_repeat(' ', (int) ceil($padding));
        
        return $leftPadding . $text . $rightPadding;
    }
}
