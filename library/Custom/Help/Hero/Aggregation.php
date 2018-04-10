<?php
/**
 * Aggregation
 *
 * Aggregate and summate all the data from Acquisition
 * Do some extra preprocessing work to make Presentation a lot easier to work with later
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

class Aggregation
{

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
        $heroes = (new Heroes(Folder\Mapper::getInstance(), UserAccessMapper::getInstance()))->findHeroes();

        /* Build a hierarchy of data
         */
        $aggregatedData = [];

        /** @var array $assessmentData */
        foreach ($data as $userId => $assessmentData) {
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

        return $aggregatedData;
    }
}