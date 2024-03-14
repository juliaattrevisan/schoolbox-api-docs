<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Firebase;

use Alaress\Web\Output\DataGenerator\IDataGenerator;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\Service;
use Schoolbox\MobileApp\ServiceMapper;

/**
 * Data generator for Firebase configuration.
 *
 * This data generator outputs:
 * * App identifiers, public configuration and web push keys of any Firebase
 *   delivery providers
 */
class DataGenerator implements IDataGenerator
{
    /**
     * Constructor
     *
     * @param ServiceMapper $serviceMapper Service mapper, required for finding a configured Firebase app.
     */
    public function __construct(private readonly ServiceMapper $serviceMapper)
    {
    }

    /**
     * @param array|null $chromeData
     * @return array
     */
    public function generate(?array $chromeData = []): array
    {
        return [
            'apps' => $this->serviceMapper
                ->findByColumn('provider', Registry::PROVIDER_FIREBASE)
                ->map(fn (Service $service) => [
                    'id'                   => $service->getId(),
                    'app_identifier'       => $service->getAppIdentifier(),
                    'public_configuration' => $service->getPublicConfiguration(),
                    'web_push_public_key'  => $service->getWebPushPublicKey(),
                ]),
        ];
    }
}
