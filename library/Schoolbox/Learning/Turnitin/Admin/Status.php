<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Admin;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Turnitin\Api;
use Schoolbox\Learning\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Turnitin\Exception\Credentials;
use Schoolbox\Learning\Turnitin\Helper;

/**
 * Get a payload that represents the status lights on the admin homepage
 */
class Status
{
    /**
     * @var IConfigLoader
     */
    private IConfigLoader $configLoader;


    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }


    /**
     * Determine the status of the Turnitin integration (if configured)
     *
     * @return array
     */
    public function generate(): array
    {
        /* Default payload
         * Turnitin has to be configured, otherwise it won't appear in the list
         */
        $payload = [
            'name' => 'SimCheck',
            'url'  => 'https://turnitin.statuspage.io/',
            'show' => false,
            'available' => false,
            'message' => 'Offline'
        ];

        /* This was configured with enough data to make an attempt to connect
         * There's something to show
         */
        if ((new Helper($this->configLoader))->isConfigured()) {
            $payload['show'] = true;

            try {
                $api = new Api(
                    $this->configLoader->get('site_version'),
                    $this->configLoader->get('turnitin_api_host'),
                    $this->configLoader->get('turnitin_api_key')
                );

                $result = $api->test();

                /* We tried to make a connection, but something went wrong
                 */
                if (null === $result) {
                    return $payload;
                }

                $payload['name']      = $result['product_name'];
                $payload['available'] = true;
                $payload['message']   = 'Online';

                return $payload;
            } catch (Credentials $e) {
                /* Bad credentials will stop you from making a proper connection
                 */
                $payload['available'] = true;
                $payload['message']   = 'Auth failed';
            } catch (GuzzleException | BadEndpoint | Exception $e) {
                /* Some kind of networky issue; noop
                 * Catch exception but do nothing so default $payload will be returned.
                 */
            }
        }

        return $payload;
    }
}
