<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Jsonl;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;

/**
 * Emits one JSONL record describing the store.
 *
 * @since 3.0.0
 */
class StoreProvider extends AbstractProvider
{
    public function provide(OutputContextInterface $context): iterable
    {
        $store = $context->getStore();

        yield $this->encodeJsonl([
            'entity_type'    => 'store',
            'entity_id'      => (int) $store->getId(),
            'store_code'     => $store->getCode(),
            'store_name'     => (string) $store->getName(),
            'url'            => $context->getBaseUrl(),
            'currency'       => $context->getCurrencyCode(),
            'locale'         => $context->getLocaleCode(),
            'embedding_text' => trim($store->getName() . ' ' . $context->getBaseUrl()),
        ]);
    }
}
