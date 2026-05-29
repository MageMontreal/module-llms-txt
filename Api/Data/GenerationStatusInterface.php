<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api\Data;

/**
 * Read-only DTO describing the result of a single generation pass for one
 * store / format combination.
 *
 * @api
 * @since 3.0.0
 */
interface GenerationStatusInterface
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_PENDING = 'pending';

    public function getStoreCode(): string;

    public function getFormat(): string;

    public function getStatus(): string;

    /**
     * Unix timestamp of the last attempted generation (success or failure).
     */
    public function getLastAttemptAt(): ?int;

    /**
     * Unix timestamp of the last successful generation.
     */
    public function getLastSuccessAt(): ?int;

    public function getByteSize(): int;

    public function getItemCount(): int;

    public function getDurationSeconds(): float;

    /**
     * Last error message (only meaningful when status is STATUS_FAILED).
     */
    public function getErrorMessage(): ?string;
}
