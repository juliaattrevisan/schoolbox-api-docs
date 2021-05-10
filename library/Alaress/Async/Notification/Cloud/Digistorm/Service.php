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
use Schoolbox\Config\Loader as ConfigLoader;
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
     * Base values to pass in the payload to requests to the Digistorm
     * notification API.
     */
    private const BASE_PAYLOAD = [
        'id_type'  => 'username',
        'platform' => 'schoolbox',
        'module'   => 'messages',
    ];

    /**
     * API key for the service
     */
    private string $key;

    /**
     * The app's title: this title may be used as the title of notifications.
     */
    private string $appTitle;

    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $key
     * @param string         $appTitle
     */
    public function __construct(
        RegistryMapper $registryMapper,
        $appIdentifier,
        string $key,
        string $appTitle
    ) {
        parent::__construct($registryMapper, $appIdentifier);
        $this->key      = $key;
        $this->appTitle = $appTitle;
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
            'X-Auth-Token' => $this->key,
            'Content-Type' => 'application/json',
        ];
        $payload            = self::BASE_PAYLOAD;
        $payload['title']   = $this->appTitle;
        $payload['message'] = $title;
        $payload['ids']     = $tokens;

        // need a HTTP client (should this be injected?)
        $client = new Client();
        try {
            $response = $client->post(
                self::BASE_URL,
                [
                    RequestOptions::BODY    => json_encode($payload),
                    RequestOptions::HEADERS => $headers,
                ]
            );

            // Check the response: maybe not all IDs exist?
            // That might be an issue worth checking?
            // Maybe also check rate limit, as the response contains an
            // "X-RateLimit-Remaining" header.
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
