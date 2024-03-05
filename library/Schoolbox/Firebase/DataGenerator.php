<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Firebase;

use Alaress\Web\Output\DataGenerator\IDataGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\ServiceMapper;

/**
 * Data generator for Firebase configuration.
 *
 * This data generator outputs:
 * * Global Firebase configs for public config, private key, web push public key
 * * App identifiers of any Firebase-based delivery providers
 */
class DataGenerator implements IDataGenerator
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config        Config loader, required for global Firebase configs.
     * @param ServiceMapper $serviceMapper Service mapper, required for finding a configured Firebase app.
     */
    public function __construct(private readonly IConfigLoader $config, private readonly ServiceMapper $serviceMapper)
    {
    }

    /**
     * @param array|null $chromeData
     * @return array
     */
    public function generate(?array $chromeData = []): array
    {
        return [
            'publicConfig'     => $this->config->get(ConfigMapper::NAME_FIREBASE_PUBLIC_CONFIG),
            'privateKey'       => json_decode($this->config->get(ConfigMapper::NAME_FIREBASE_PRIVATE_KEY), true),
            'webPushPublicKey' => $this->config->get(ConfigMapper::NAME_FIREBASE_WEB_PUSH_PUBLIC_KEY),
            'appIdentifiers'   => $this->serviceMapper->findScalarByPredicates(
                'app_identifier',
                $this->serviceMapper->getPredicateBuilder()->equals('provider', Registry::PROVIDER_FIREBASE),
            ),
        ];
    }
}
