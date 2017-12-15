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

use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\ProfessionalDevelopment;


class Prefilter
{
    /**
     * @var ProfessionalDevelopment\Mapper
     */
    private $pdMapper;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * Prefilter constructor.
     * @param ProfessionalDevelopment\Mapper $pdMapper
     * @param UserAccessMapper               $userAccessMapper
     */
    public function __construct(
        ProfessionalDevelopment\Mapper $pdMapper,
        UserAccessMapper $userAccessMapper
    ) {
        $this->pdMapper = $pdMapper;
        $this->userAccessMapper = $userAccessMapper;
    }


    /**
     * Cache all the Professional Development details into a hashtable
     * Dehydrate some of this data to be as lean as possible
     *
     * @param Collection $allPd
     * @return array
     */
    private function loadProfessionalDevelopment(Collection $allPd) :array
    {
        $pdHashtable = [];

        /** @var ProfessionalDevelopment\Model $currentPd */
        foreach ($allPd as $currentPd) {
            $pdHashtable[$currentPd->getUser()->getId()] = $currentPd->getNumber();
        }

        return $pdHashtable;
    }


    /**
     * Find all the users who have Professional Development details
     *
     * Store them in a hashtable so we can look up users in a folder later, in Acquisition
     *
     * @param array    $states
     * @param int|null $includeAccredited
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findInvolvement(array $states, ?int $includeAccredited)
    {
        /** @var Collection $users */
        if ($includeAccredited) {
            $users = $this->pdMapper->findByColumn('registration_state', $states);
        }
        else {
            $users = $this->pdMapper->findByColumnList([
                'registration_state' => $states,
                'accredited_at' => null
            ]);
        }

        /* Store folder-user relations and a hashtable of Professional Development records
         * This will save us some db calls later when we assemble data in Acquisition
         */
        $involvement = [
            'hierarchy' => [],
            'pd' => $this->loadProfessionalDevelopment($users)
        ];

        foreach ($users as $currentUser) {
            $accesses = $this->userAccessMapper->findAllGroupsForUser($currentUser->getUser());

            /** @var UserAccess $currentAccess */
            foreach ($accesses as $currentAccess) {
                $folderId = $currentAccess->getServiceIdentifier();
                $userId = $currentAccess->getUser()->getId();

                $involvement['hierarchy'][$folderId][$userId] = $currentAccess->getUser();
            }
        }

        ksort($involvement);

        return $involvement;
    }
}