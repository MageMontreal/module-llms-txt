<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Console\Command;

use Angeo\LlmsTxt\Api\Data\GenerationStatusInterface;
use Angeo\LlmsTxt\Api\GenerationStatusRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento angeo:llms:status`
 *
 * Prints the last-generation status table.
 *
 * @since 3.0.0
 */
class StatusCommand extends Command
{
    public function __construct(
        private readonly GenerationStatusRepositoryInterface $statusRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:llms:status')
            ->setDescription('Show per-store/per-format llms file generation status.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statuses = $this->statusRepository->getAll();

        if ($statuses === []) {
            $output->writeln('<comment>No generations recorded yet.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Store', 'Format', 'Status', 'Last success (UTC)', 'Size', 'Items', 'Time']);

        foreach ($statuses as $s) {
            $table->addRow([
                $s->getStoreCode(),
                $s->getFormat(),
                $this->colorStatus($s->getStatus()),
                $s->getLastSuccessAt() ? gmdate('Y-m-d H:i:s', $s->getLastSuccessAt()) : '—',
                $this->formatBytes($s->getByteSize()),
                $s->getItemCount(),
                sprintf('%.2fs', $s->getDurationSeconds()),
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function colorStatus(string $status): string
    {
        return match ($status) {
            GenerationStatusInterface::STATUS_SUCCESS => '<info>success</info>',
            GenerationStatusInterface::STATUS_FAILED  => '<error>failed</error>',
            default => '<comment>' . $status . '</comment>',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
