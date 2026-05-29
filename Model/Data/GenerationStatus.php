<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Data;

use Angeo\LlmsTxt\Api\Data\GenerationStatusInterface;

/**
 * Immutable {@see GenerationStatusInterface} value object.
 *
 * @since 3.0.0
 */
class GenerationStatus implements GenerationStatusInterface
{
    public function __construct(
        private readonly string $storeCode,
        private readonly string $format,
        private readonly string $status,
        private readonly ?int $lastAttemptAt,
        private readonly ?int $lastSuccessAt,
        private readonly int $byteSize,
        private readonly int $itemCount,
        private readonly float $durationSeconds,
        private readonly ?string $errorMessage
    ) {
    }

    public function getStoreCode(): string         { return $this->storeCode; }
    public function getFormat(): string            { return $this->format; }
    public function getStatus(): string            { return $this->status; }
    public function getLastAttemptAt(): ?int       { return $this->lastAttemptAt; }
    public function getLastSuccessAt(): ?int       { return $this->lastSuccessAt; }
    public function getByteSize(): int             { return $this->byteSize; }
    public function getItemCount(): int            { return $this->itemCount; }
    public function getDurationSeconds(): float    { return $this->durationSeconds; }
    public function getErrorMessage(): ?string     { return $this->errorMessage; }
}
