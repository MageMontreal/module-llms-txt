<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Console\Command;

use Angeo\LlmsTxt\Model\Generator\JsonlGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsTxtGenerator;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento angeo:llms:validate [--store=...]`
 *
 * Lints the generated files for spec compliance:
 *  - llms.txt MUST start with an H1
 *  - llms.txt MUST contain exactly one blockquote summary
 *  - JSONL MUST have one valid JSON object per line
 *
 * @since 3.0.0
 */
class ValidateCommand extends Command
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly LlmsTxtGenerator $llmsTxtGenerator,
        private readonly JsonlGenerator $jsonlGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:llms:validate')
            ->setDescription('Validate generated llms.txt + JSONL files against the spec.')
            ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store code (default: all)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeCode = $input->getOption('store');
        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : array_filter(
                $this->storeManager->getStores(),
                static fn($s) => !method_exists($s, 'isActive') || $s->isActive()
            );

        $errors = 0;
        foreach ($stores as $store) {
            $errors += $this->validateForStore($store->getCode(), $output);
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function validateForStore(string $code, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln(sprintf('<comment>Store: %s</comment>', $code));

        $errors = 0;

        $txtPath = $this->llmsTxtGenerator->getFilePath($code);
        if (file_exists($txtPath)) {
            $errors += $this->validateTxt($txtPath, $output);
        } else {
            $output->writeln('  <comment>llms.txt not generated yet</comment>');
        }

        $jsonlPath = $this->jsonlGenerator->getFilePath($code);
        if (file_exists($jsonlPath)) {
            $errors += $this->validateJsonl($jsonlPath, $output);
        } else {
            $output->writeln('  <comment>JSONL not generated yet</comment>');
        }

        return $errors;
    }

    private function validateTxt(string $path, OutputInterface $output): int
    {
        $errors = 0;
        $content = (string) file_get_contents($path);
        $lines = explode("\n", $content);

        // Rule 1: First non-empty line must be an H1.
        $first = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $first = $line;
                break;
            }
        }
        if (!str_starts_with($first, '# ')) {
            $output->writeln('  <error>✗ llms.txt: first line is not an H1</error>');
            $errors++;
        } else {
            $output->writeln('  <info>✓ llms.txt: H1 present</info>');
        }

        // Rule 2: Exactly one blockquote summary line (a "> " line near the top).
        $bqCount = 0;
        $seenH2  = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, '## ')) {
                $seenH2 = true;
                break;
            }
            if (str_starts_with($line, '> ')) {
                $bqCount++;
            }
        }
        if ($bqCount === 0) {
            $output->writeln('  <comment>! llms.txt: no blockquote summary (recommended by spec)</comment>');
        } elseif ($bqCount > 1) {
            $output->writeln('  <comment>! llms.txt: multiple blockquote lines — spec recommends a single summary line</comment>');
        } else {
            $output->writeln('  <info>✓ llms.txt: single blockquote summary</info>');
        }

        return $errors;
    }

    private function validateJsonl(string $path, OutputInterface $output): int
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            $output->writeln('  <error>✗ JSONL: cannot open file</error>');
            return 1;
        }

        $bad = 0;
        $total = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\n");
            if ($line === '') {
                continue;
            }
            $total++;
            json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $bad++;
            }
        }
        fclose($handle);

        if ($bad > 0) {
            $output->writeln(sprintf('  <error>✗ JSONL: %d/%d lines invalid</error>', $bad, $total));
            return 1;
        }
        $output->writeln(sprintf('  <info>✓ JSONL: %d valid records</info>', $total));
        return 0;
    }
}
