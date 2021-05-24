<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

/**
 * Class Registry
 *
 * Remember an arbitrary $data array via a string $key
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Registry
{

    /**
     * @var array
     */
    private $registry = [];


    /**
     * Remember $data via $key
     *
     * @param string $key
     * @param array  $data
     */
    public function remember(string $key, array $data): void
    {
        $this->registry[$key] = $data;
    }


    /**
     * Recall $data via $key
     *
     * @param string $key
     * @return array|null
     */
    public function recall(string $key): ?array
    {
        return $this->registry[$key] ?? null;
    }


    /**
     * Return all the entire stack
     *
     * @return array
     */
    public function recallAll(): array
    {
        return $this->registry;
    }


    /**
     * Just call the Captain Keyes that you remembered in this instance
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->registry);
    }
}
