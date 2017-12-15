<?php
/**
 * NESA User Involvement Prefilter
 *
 * Find the users who have Professional Development details and return a list of folders that they're in.
 * We can intersect this with Acquisition's Course Implementation list to minimise the number of locations
 * to investigate for rubric data. Unfortunately there isn't a way to find out which assessments people are in
 * (rubrics are stored for an assessment at the moment) so we need a bottom-up list to limit a top-down diggery
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help\Nesa;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\ProfessionalDevelopment;


class Prefilter
{

    /**
     * Find all the users who have Professional Development details
     *
     * Store them in a hashtable so we can look up users in a folder later, in Acquisition
     *
     * @return array
     */
    public function findInvolvement()
    {
        $involvement = [];

        $users = ProfessionalDevelopment\Mapper::getInstance()->findAll();

        foreach ($users as $currentUser) {
            $accesses = UserAccessMapper::getInstance()->findAllGroupsForUser($currentUser->getUser());

            /** @var UserAccess $currentAccess */
            foreach ($accesses as $currentAccess) {
                $folderId = $currentAccess->getServiceIdentifier();
                $userId = $currentAccess->getUser()->getId();

                $involvement[$folderId][$userId] = $currentAccess->getUser();
            }
        }

        ksort($involvement);

        return $involvement;
    }
}