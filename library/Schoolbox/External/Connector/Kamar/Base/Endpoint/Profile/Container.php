<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

/**
 * Class Container
 *
 * For staff members, we remember their (external id, details) tuples
 * because other xml files use teacher codes (e.g. BKE) to represent a teacher
 * but you don't have an easy way to find out what their details are such as
 * their full name etc. because it's contained in some other xml file
 *
 * Therefore as we process each profile xml, we remember the staff members
 * for easier reference later
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile
 */
class Container
{

    /**
     * @var array
     */
    private $registry = [];


    /**
     * Remember a staff member's details, using their externalId as a key
     *
     * @param string $externalId
     * @param string $title
     * @param string $firstname
     * @param string $lastname
     * @param string $username
     */
    public function remember(string $externalId, string $title, string $firstname, string $lastname, string $username): void
    {
        $this->registry[$externalId] = [
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
}
