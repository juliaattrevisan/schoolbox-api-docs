<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup;

use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;

class Status
{



    /**
     * Data equivalents of each status, phrased programmatically
     */
    public const array STATUSES = [
        self::STATUS_INCOMPLETE,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REQUIRES_ASSISTANCE,
        self::STATUS_SUBMITTED
    ];


    /**
     * Human-readable equivalents of each status
     *
     * You might find this convenient to access as a public prop, in which case you can elevate it
     * from private to public upon first encounter.
     */
    private const array STATUS_HR = [
        self::STATUS_INCOMPLETE => 'Incomplete',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_REQUIRES_ASSISTANCE => 'Requires Assistance',
        self::STATUS_SUBMITTED => 'Submitted'
    ];


    /**
     * Decide the current status of a particular response and returns the various metadata
     * relevant per state which you will probably just arbitrarily render if extant.
     *
     * Returns a set of (status, meta array) tuples.
     *
     * @param ResponseModel|null $authoritativeResponse
     * @return array
     */
    public function calculate(?ResponseModel $authoritativeResponse): array
    {

    }


    /**
     * Get a human-readable equivalent of a status derivation
     *
     * @param ResponseModel|null $authoritativeResponse
     * @return string
     */
    public function getHumanReadableStatus(?ResponseModel $authoritativeResponse): string
    {
        $status = $this->calculate($authoritativeResponse);

        return self::STATUS_HR[$status['status']] ?? '';
    }
}
