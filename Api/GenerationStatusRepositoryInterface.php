<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

use Angeo\LlmsTxt\Api\Data\GenerationStatusInterface;

/**
 * Repository tracking the last successful (and last attempted) generation for each
 * store / format combination.
 *
 * Backs the admin status panel and the {@code bin/magento angeo:llms:status} CLI.
 * Persisted via core_config_data (no schema additions needed) — see
 * {@see \Angeo\LlmsTxt\Model\Repository\GenerationStatusRepository}.
 *
 * @api
 * @since 3.0.0
 */
interface GenerationStatusRepositoryInterface
{
    /**
     * Get the status entry for a store/format, or null if never generated.
     */
    public function get(string $storeCode, string $format): ?GenerationStatusInterface;

    /**
     * Record a successful generation.
     */
    public function recordSuccess(
        string $storeCode,
        string $format,
        int $byteSize,
        int $itemCount,
        float $durationSeconds
    ): void;

    /**
     * Record a failed generation attempt.
     */
    public function recordFailure(string $storeCode, string $format, string $errorMessage): void;

    /**
     * Get all known status entries (one per store/format combination).
     *
     * @return GenerationStatusInterface[]
     */
    public function getAll(): array;
}
