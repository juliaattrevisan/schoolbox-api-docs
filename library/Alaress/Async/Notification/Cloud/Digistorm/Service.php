<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Digistorm;

use Alaress\Async\Notification\Cloud\AbstractService;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;

/**
 * Digistorm notification service
 */
class Service extends AbstractService
{
    /**
     * API key for the service
     */
    private string $key;

    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $key
     */
    public function __construct(RegistryMapper $registryMapper, string $appIdentifier, string $key)
    {
        parent::__construct($registryMapper, $appIdentifier);
        $this->key = $key;
    }

    /**
     * @inheritdoc
     */
    public function getProviderName(): string
    {
        return Registry::PROVIDER_DIGISTORM;
    }

    /**
     * @inheritdoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0)
    {
        // TODO: Implement propagate() method.
        return true;
    }
}
