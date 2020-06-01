<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Schoolbox\Calendar\Event;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


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
     * @var int
     */
    private $intention;


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


    /**
     * @return int
     */
    public function getIntention(): int
    {
        return $this->intention;
    }


    /**
     * @param int $intention
     * @return $this
     */
    public function setIntention(int $intention): self
    {
        $this->intention = $intention;
        return $this;
    }
}
