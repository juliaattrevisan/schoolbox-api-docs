<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

/**
 * Class Registry
 *
 * Remember an arbitrary $data array via a string $key
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Registry
{
    /**
     * @var array
     */
    private array $registry = [];


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
     * Return the count of items
     *
     * @return ?int
     */
    public function count(): ?int
    {
        return count($this->registry);
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


    /**
     * Find a staff member in the registry whose person id === $id
     * (Used as a reverse lookup)
     *
     * @param int    $id
     * @param string $type
     * @return array|null
     */
    public function findById(int $id, string $type): ?array
    {
        /* Note that $data['person']['id'] might not have been an int when it went in here
         */
        foreach ($this->registry as $key => $data) {
            if ((int)$data['person']['id'] === $id && $data['person']['type'] === $type) {
                return $data;
            }
        }

        return null;
    }
}
