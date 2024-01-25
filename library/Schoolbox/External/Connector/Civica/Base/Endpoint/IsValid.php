<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

class IsValid
{
    /**
     * @param string $now
     */
    public function __construct(private string $now)
    {
    }


    /**
     * Decide if we care about this $alert struct
     *
     * @param array $data
     * @param string $startDateKey
     * @param string $endDateKey
     * @return bool
     */
    public function isSatisifiedBy(
        array $data,
        string $startDateKey = 'startDate',
        string $endDateKey = 'endDate'
    ): bool {
        /* Start date is in the past
         * Look at the end date
         */
        if ($data[$startDateKey] && $this->now >= $data[$startDateKey]) {
            if ((null === $data[$endDateKey]) || ('' === $data[$endDateKey])) {
                /* No end date specified;
                 * Alert is valid at the moment
                 */
                return true;
            }

            /* End date was specified, and it's in the future
             * Alert is valid at the moment
             */
            if ($this->now < $data[$endDateKey]) {
                return true;
            }
        }

        /* Alert starts in the future, or it ended in the past
         * Ignore it
         */
        return false;
    }
}
