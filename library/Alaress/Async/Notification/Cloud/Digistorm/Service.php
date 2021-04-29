<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Digistorm;

use Alaress\Async\Notification\Cloud\AbstractService;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;

/**
 * Digistorm notification service
 */
class Service extends AbstractService
{
    /**
     * The URL to which notifications are pushed
     */
    private const BASE_URL = 'https://api.notifications.digistorm.io/v1/push';

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
        $headers = [
            'X-Auth-Token: ' . $this->key,
        ];
        $payload = [
            'title'       => $title,
            'ids'         => $tokens,
            'custom_data' => [
                'notice_id' => $id,
            ],
        ];

        // need a HTTP client (should this be injected?)
        $client = new Client();
        try {
            $response = $client->post(
                self::BASE_URL,
                [
                    RequestOptions::BODY    => $payload,
                    RequestOptions::HEADERS => $headers,
                ]
            );

            // Check the response: maybe not all IDs exist?
        } catch (RuntimeException $e) {
            if ($e->getCode() === 401) {
                // your API key is bad: don't try to do anything else
                throw $e;
            }
            $this->getLog()->warning($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }
}
