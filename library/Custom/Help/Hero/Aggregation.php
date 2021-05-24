<?php

/**
 * Aggregation
 *
 * Aggregate and summate all the data from Acquisition
 * Do some extra preprocessing work to make Presentation a lot easier to work with later
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Hero;

use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Aggregation
{

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * Aggregation constructor.
     * @param Folder\Mapper    $folderMapper
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(Folder\Mapper $folderMapper, UserAccessMapper $userAccessMapper)
    {
        $this->folderMapper = $folderMapper;
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * Aggregate data to work out who qualified for Hero status
     *
     * @param array $data Data from Acquisition
     * @return array
     * @throws \DomainException
     */
    public function aggregate(array $data): array
    {
        /* Figure out who the current heroes are
         */
        $heroes = (new Heroes($this->folderMapper, $this->userAccessMapper))->findHeroes();

        /* A list of heroes who have been doing the Teacher Course
         * Some heroes have done an older version, or different assessments
         * We don't want to exclude those from the report, so remember the guys we saw
         * and include the difference at the end
         */
        $seen = [];

        /* Build a hierarchy of data
         */
        $aggregatedData = [];

        /** @var array $assessmentData */
        foreach ($data as $userId => $assessmentData) {
            $seen[] = $userId;

            foreach ($assessmentData as $currentAssessmentData) {
                if (!array_key_exists($userId, $aggregatedData)) {
                    $aggregatedData[$userId] = [
                        'firstname' => $currentAssessmentData['firstname'],
                        'lastname' => $currentAssessmentData['lastname'],
                        'fullname' => "{$currentAssessmentData['firstname']} {$currentAssessmentData['lastname']}",
                        'folderId' => $currentAssessmentData['folderId'],
                        'folderName' => $currentAssessmentData['folderName'],
                        'qualified' => false,
                        'heroised' => \array_key_exists($userId, $heroes),
                        'assessments' => []
                    ];
                }

                /* Figure out if this assessment spec stack already exists
                 *
                 * Reassignation shouldn't happen for Due Works; Acquisition gets unique Due Works with the highest
                 * mark already, so I didn't need to throw data away here
                 *
                 * However, with quizzes, for some reason subqueries didn't work so I have to find the one with the
                 * highest mark manually
                 */
                $key = $currentAssessmentData['specId'];

                $assessmentSpecNotStackedYet = !array_key_exists($key, $aggregatedData[$userId]['assessments']);
                $existingSpecHasLowerMark = ((float)$aggregatedData[$userId]['assessments'][$key]['mark'] < (float)$currentAssessmentData['mark']);

                /* Stack it if it doesn't exist
                 * Update the existing stack record if it already exists but mark was lower than current record
                 */
                if ($assessmentSpecNotStackedYet || $existingSpecHasLowerMark) {
                    $aggregatedData[$userId]['assessments'][$key] = [
                        'mark' => $currentAssessmentData['mark'],
                    ];
                }
            }
        }

        /* Identify those who qualified
         */
        $qualification = new ValidQualification();

        foreach ($aggregatedData as $userId => &$userData) {
            if ($qualification->isSatisfiedBy($userData)) {
                $userData['qualified'] = true;
            }
        }

        /* Safety measure to ensure that $userData isn't accidentally changed
         * https://stackoverflow.com/questions/3307409/php-pass-by-reference-in-foreach
         */
        unset($userData);

        /* All the older heroes who do not meet the current qualification criteria
         * but met some older version of it, at some point in the past, and are already heroes
         */
        $existingHeroes = array_keys($heroes);
        $olderHeroes = array_diff($existingHeroes, $seen);

        foreach ($olderHeroes as $userId) {

            /** @var User $hero */
            $hero = $heroes[$userId];

            $aggregatedData[$userId] = [
                'firstname' => $hero->getFirstname(),
                'lastname' => $hero->getLastname(),
                'fullname' => $hero->getFullName(),
                'folderId' => $heroes['folderId'],
                'folderName' => $heroes['folderName'],
                'qualified' => false,
                'heroised' => true,
                'assessments' => []
            ];
        }

        return $aggregatedData;
    }
}
