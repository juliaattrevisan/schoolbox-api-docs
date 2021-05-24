<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Schoolbox\Calendar\Event;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class Model
{
    use ModelTrait\Id;

    /**
     * RSVP Status Pending: waiting for intent to be declared
     * RSVP Status Accepted: user accepted invitation
     * RSVP Status Declined: user declined invitation
     */
    public const STATUS_PENDING = 1;
    public const STATUS_ACCEPTED = 2;
    public const STATUS_DECLINED = 3;


    /**
     * Suggested css classes corresponding to each status
     */
    public const CLASSES = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_ACCEPTED => 'accepted',
        self::STATUS_DECLINED => 'declined'
    ];


    /**
     * Attendance levels - how this event restricts guests' attendance
     */
    public const LEVEL_NO_ATTENDANCE = 0;
    public const LEVEL_INVITE_ONLY = 1;
    public const LEVEL_OPEN_ATTENDANCE = 2;


    /**
     * @var User
     */
    private $user;


    /**
     * @var Event
     */
    private $event;


    /**
     * @var int
     */
    private $status;


    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return $this
     *
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return Event
     */
    public function getEvent(): Event
    {
        return $this->event;
    }


    /**
     * @param Event $event
     * @return $this
     */
    public function setEvent(Event $event): self
    {
        $this->event = $event;
        return $this;
    }


    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }


    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }
}
