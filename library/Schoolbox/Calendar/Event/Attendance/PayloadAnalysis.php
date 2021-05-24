<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Schoolbox\User;

/**
 * Class PayloadAnalysis
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class PayloadAnalysis
{

    /**
     * @var int|null
     */
    private $maxAttendees;


    /**
     * @var array
     */
    private $guests;


    /**
     * PayloadAnalysis constructor.
     *
     * @param int|null $maxAttendees
     * @param array    $guests The result of attendanceMapper->findAttendances($event)
     */
    public function __construct(?int $maxAttendees, array $guests)
    {
        $this->maxAttendees = $maxAttendees;
        $this->guests = $guests;
    }


    /**
     * The number of Pending
     *
     * @return int
     */
    public function getNumPending(): int
    {
        return \count(array_filter($this->guests, static function ($element) {
            return $element['status'] === Model::STATUS_PENDING;
        }));
    }


    /**
     * The number of Accepts
     *
     * @return int
     */
    public function getNumAccepted(): int
    {
        return \count(array_filter($this->guests, static function ($element) {
            return $element['status'] === Model::STATUS_ACCEPTED;
        }));
    }


    /**
     * The number of Declines
     *
     * @return int
     */
    public function getNumDeclined(): int
    {
        return \count(array_filter($this->guests, static function ($element) {
            return $element['status'] === Model::STATUS_DECLINED;
        }));
    }


    /**
     * The number of invitations that are accept, or could be accepted;
     * used to ensure you don't ultimately have more accepting than max allowed
     *
     * @return int
     */
    public function getNumAttendees(): int
    {
        return $this->getNumAccepted();
    }


    /**
     * The number of Available places remaining into which someone can be invited
     * to attend the event
     *
     * Number of available places could be null, 0, or many
     *
     * @return int|null
     */
    public function getNumAvailable(): ?int
    {
        $maxAttendees = $this->maxAttendees;
        $numAttendees = $this->getNumAttendees();
        $available = null;

        if (null !== $maxAttendees) {
            $available = $maxAttendees - $numAttendees;

            if ($available < 0) {
                $available = 0;
            }
        }

        return $available;
    }


    /**
     * Find $user's current RSVP status
     * null if they weren't even on the list
     *
     * @param User $user
     * @return int|null
     */
    public function getUserStatus(User $user): ?int
    {
        foreach ($this->guests as $guest) {
            if ($guest['id'] === $user->getId()) {
                return $guest['status'];
            }
        }

        return null;
    }
}
