<?php
/**
 * ValidOpenReferrer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics;

class ValidOpenReferrer
{
    /**
     * These consts are valid ones
     */
    private const VALID_REFERRER = [
        Mapper::REFERRER_DASHBOARD,
        Mapper::REFERRER_HOMEPAGE,
        Mapper::REFERRER_INDEX,
        Mapper::REFERRER_ARCHIVE,
        Mapper::REFERRER_EMAIL,
        Mapper::REFERRER_SAVED,
        Mapper::REFERRER_SEARCH,
    ];


    /**
     * Referrers can be null or one of the prescribed values
     *
     * @param null|string $referrer
     * @return bool
     */
    public function isSatisfiedBy(?string $referrer): bool
    {
        if (null === $referrer) {
            return true;
        }

        return \in_array($referrer, self::VALID_REFERRER, true);
    }
}
