<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Firebase;

use Alaress\Async\Notification\Cloud\AbstractService;
use Kreait\Firebase\Factory;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use SensitiveParameter;

/**
 * Firebase-based notification service.
 */
class Service extends AbstractService
{
    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $privateKey
     */
    public function __construct(
        RegistryMapper $registryMapper,
        $appIdentifier,
        #[SensitiveParameter] private string $privateKey,
    ) {
        parent::__construct($registryMapper, $appIdentifier);
    }


    /**
     * @inheritDoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0)
    {
        $factory = (new Factory())
            ->withServiceAccount($this->privateKey);
    }

    /**
     * @inheritDoc
     */
    public function getProviderName()
    {
        return Registry::PROVIDER_FIREBASE;
    }
}
