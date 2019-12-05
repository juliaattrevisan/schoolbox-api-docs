<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan;

use Schoolbox\User;

/**
 * Class ValidJuniorSchoolStudent
 *
 * Decide if a student is in the Junior School Students campus
 *
 * @package Custom\Diocesan
 */
class ValidJuniorSchoolStudent
{

    /**
     * This is the Junior School Students campus code
     */
    private const CAMPUS_JUNIOR_SCHOOL = 'JNR';


    /**
     * Determine if this->user is a Junior School Student by looking at
     * their campus involvement
     *
     * @param User $user
     * @return bool
     */
    public function isSatisfiedBy(User $user): bool
    {
        return null !== $user->getCampusCollection()->getBy('extTimetableCode', self::CAMPUS_JUNIOR_SCHOOL);
    }
}
