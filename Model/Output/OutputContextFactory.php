<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Factory for {@see OutputContext}.
 *
 * Each generation pass (one store × one format) gets a fresh context. We don't use
 * an ObjectManager-generated factory because the constructor takes scalars whose
 * combination is the whole point of the factory.
 *
 * @since 3.0.0
 */
class OutputContextFactory
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config
    ) {
    }

    /**
     * @param string                $format   One of {@see OutputContextInterface}::FORMAT_*
     * @param string                $verbosity One of {@see OutputContextInterface}::VERBOSITY_*
     */
    public function create(
        StoreInterface $store,
        string $format,
        string $verbosity
    ): OutputContextInterface {
        return new OutputContext(
            $store,
            $format,
            $verbosity,
            $this->config->getCustomerGroupId($store),
            $this->scopeConfig
        );
    }
}
