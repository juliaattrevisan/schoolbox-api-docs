<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class Status implements IStatus
{
    /**
     * Path to the config file
     */
    private const CONFIG_FILE = __DIR__ . '/config.json';

    /**
     * @inheritDoc
     */
    public function isReachable(): bool
    {
        $config = $this->loadConfig();
        (new Api($config['token']))->retrieve('GET', 'people?$first=1');

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getReport(): ?array
    {
        try {
            $config = $this->loadConfig();
            $api = new Api($config['token']);
        } catch (\RuntimeException | \DomainException $e) {
            return [[
                'message' => 'Cannot access api endpoint',
                'reason' => $e->getMessage(),
                'status' => ISpecification::STATUS_ERROR
            ]];
        }

        return (new Specification($api))->check();
    }

    /**
     * Load configuration from the local config.json file
     *
     * @return array
     * @throws \RuntimeException
     */
    private function loadConfig(): array
    {
        if (!file_exists(self::CONFIG_FILE)) {
            throw new \RuntimeException(
                'Edlink config file not found. Copy config.json.example to config.json and add your integration access token.'
            );
        }

        $config = json_decode(file_get_contents(self::CONFIG_FILE), true, 512, JSON_THROW_ON_ERROR);

        if (empty($config['token'])) {
            throw new \RuntimeException('Edlink config.json must contain a non-empty "token" field.');
        }

        return $config;
    }
}
