<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Directory\Staff;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper    $errorMapper
     * @param UserMapper     $userMapper
     * @param \External_User $externalUser
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private UserMapper $userMapper,
        private \External_User $externalUser
    ) {
    }


    /**
     * Provide list of staff members in this directory
     */
    public function getList(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@staffDirectory/index.twig', [
            'payload' => $this->generatePayload(),
        ])->setTitle('Staff Directory'));
    }

    /**
     * Generate a set of staff members but augment with additional info
     *
     * @return array
     */
    private function generatePayload(): array
    {
        // Agglutinate multiple JobPositionDescription
        $staffs = array_reduce(
            $this->externalUser->getStaffContactListExtended(),
            [$this, 'mergeStaffRecords'],
            []
        );

        foreach ($staffs as &$staff) {
            /* Condense into one csv
             */
            $staff['JobPositionDescription'] = implode(', ', $staff['JobPositionDescription']);

            /* Manufacture a portrait url
             * FIXME You might not need this, because you could infer it on frontend.
             */
            $staff['portrait_url'] = sprintf('/portrait.php?userId=%s&size=square32', $staff['StaffID']);
        }

        unset($staff);

        return $staffs;
    }


    /**
     * Merge staff records method, used as an array_reduce() callback to merge
     * job position descriptions.
     *
     * @param array $mergedRecords
     * @param array $record
     *
     * @return array
     */
    protected function mergeStaffRecords(array $mergedRecords, array $record): array
    {
        $staffId = $record['StaffID'];

        if (!array_key_exists($staffId, $mergedRecords)) {
            $mergedRecords[$staffId] = $record;
            $mergedRecords[$staffId]['JobPositionDescription'] = array();
            $mergedRecords[$staffId]['JobPositionDescriptionAlternate'] = array();
        }

        // Only set descriptions that aren't empty and aren't dupes
        if (!empty($record['JobPositionDescription'])) {
            $mergedRecords[$staffId]['JobPositionDescription'][$record['JobPositionDescription']]
                = $record['JobPositionDescription'];
        }
        if (!empty($record['JobPositionDescriptionAlternate'])) {
            $mergedRecords[$staffId]['JobPositionDescriptionAlternate'][$record['JobPositionDescriptionAlternate']]
                = $record['JobPositionDescriptionAlternate'];
        }

        return $mergedRecords;
    }
}
