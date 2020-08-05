<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile;

/**
 * Class Registry
 *
 * Remember an externalId into a stack of externalIds
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile
 */
class Registry
{

    /**
     * @var array
     */
    private $registry = [];


    /**
     * Remember a person's details
     *
     * @param string $key
     * @param string $externalId
     * @param string $title
     * @param string $firstname
     * @param string $lastname
     * @param string $username
     */
    public function remember(string $key, string $externalId, string $title, string $firstname, string $lastname, string $username): void
    {
        $this->registry[$key] = [
            'externalId' => $externalId,
            'title' => $title,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'username' => $username
        ];
    }


    /**
     * Recall a user's details via their externalId
     *
     * @param string $externalId
     * @return array|null
     */
    public function recall(string $externalId): ?array
    {
        return $this->registry[$externalId] ?? null;
    }


    /**
     * Return all the keys we remembered
     *
     * @return array
     */
    public function recallKeys(): array
    {
        return array_keys($this->registry);
    }
}
