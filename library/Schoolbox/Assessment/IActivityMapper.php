<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment;

/**
 * Interface IActivityMapper
 *
 * General interface for Quiz and Work mappers to supply Activities
 *
 * @package Schoolbox\Assessment
 */
interface IActivityMapper 
{

    /**
     * Gets the list of activities related to student submissions.
     *
     * An activity is returned for each submission that this user has made into
     * a submission box in the given categories.
     *
     * @param int $userId
     * @param int $limit
     * @param array $folderIds
     *
     * @return ActivityCollection|Activity[]
     */
    public function getActivities($userId, $limit, array $folderIds);

}
