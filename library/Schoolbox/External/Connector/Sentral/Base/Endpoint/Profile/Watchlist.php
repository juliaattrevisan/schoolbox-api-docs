<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;

/**
 * Class Watchlist
 *
 * A diagnostic tool
 *
 * Some people's records don't work correctly for some reason
 * This allows you to look out for specific people
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile
 */
class Watchlist
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * Look out for anyone in this list
     *
     * [
     *     [$firstname, $lastname],
     *     [$firstname, $lastname],
     *     ...
     * ]
     *
     * @var array
     */
    private $people;


    /**
     * Watchlist constructor.
     *
     * @param LoggerInterface $log
     * @param array           $people
     */
    public function __construct(LoggerInterface $log, array $people)
    {
        $this->log = $log;
        $this->people = $people;
    }


    /**
     * $item is somehow relevant to this person and this person is on your watchlist,
     * so print $message and $item itself
     *
     * @param string      $message
     * @param mixed       $item
     * @param string|null $firstname
     * @param string|null $lastname
     * @return void
     */
    public function observe(string $message, $item, ?string $firstname, ?string $lastname): void
    {
        foreach ($this->people as $person) {
            if ($person[0] === $firstname && $person[1] === $lastname) {
                $this->log->critical($message);
                print_r($item);
            }
        }
    }


    /**
     * Decide whether the person you're looking at is someone on your watchlist
     * You might want to continue; in your iteration if currently you're looking
     * at nobody of interest (might save you some time)
     *
     * @param string|null $firstname
     * @param string|null $lastname
     * @return bool
     */
    public function isObserved(?string $firstname, ?string $lastname): bool
    {
        foreach ($this->people as $person) {
            if ($person[0] === $firstname && $person[1] === $lastname) {
                return true;
            }
        }

        return false;
    }
}
