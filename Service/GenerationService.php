<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Service;

use Angeo\LlmsTxt\Model\Generator\GenerationSummary;
use Angeo\LlmsTxt\Model\Generator\JsonlGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsFullTxtGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsTxtGenerator;

/**
 * Single entry point for "run every enabled generator".
 *
 * Used by the CLI command, cron, admin "Generate Now" action, and the async
 * consumer. Each generator returns its own {@see GenerationSummary}; this
 * service returns a map keyed by format.
 *
 * @since 3.0.0
 */
class GenerationService
{
    public function __construct(
        private readonly LlmsTxtGenerator $llmsTxtGenerator,
        private readonly LlmsFullTxtGenerator $llmsFullTxtGenerator,
        private readonly JsonlGenerator $jsonlGenerator
    ) {
    }

    /**
     * Run all generators.
     *
     * @param string|null $storeCode  Restrict to one store, or null for all.
     * @param array<string, bool> $skip  ['llms_txt' => true, 'llms_full_txt' => false, 'jsonl' => false]
     * @return array<string, GenerationSummary>  keyed by format
     */
    public function generateAll(?string $storeCode = null, array $skip = []): array
    {
        $summaries = [];

        if (empty($skip['llms_txt'])) {
            $summaries['llms_txt'] = $this->llmsTxtGenerator->generate($storeCode);
        }
        if (empty($skip['llms_full_txt'])) {
            $summaries['llms_full_txt'] = $this->llmsFullTxtGenerator->generate($storeCode);
        }
        if (empty($skip['jsonl'])) {
            $summaries['jsonl'] = $this->jsonlGenerator->generate($storeCode);
        }

        return $summaries;
    }
}
