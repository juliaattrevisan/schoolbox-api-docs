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
    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper Mapper for fetching registrations.
     * @param string         $appIdentifier  Identifying string for the app: matches `mobile_app.app_identifier`.
     * @param string         $privateKey     Private key for Firebase's service: matches `mobile_app.key`.
     *                                       See https://firebase-php.readthedocs.io/en/stable/setup.html for how to get
     *                                       a private key.
     * @param string         $appTitle       Title of the app: matches `config.title`.
     */
    public function __construct(
        RegistryMapper $registryMapper,
        $appIdentifier,
        #[SensitiveParameter] private string $privateKey,
        private string $appTitle,
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
