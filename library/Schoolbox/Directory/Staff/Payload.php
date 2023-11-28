<?php

namespace Schoolbox\Directory\Staff;

use Alaress\Database\Exception;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Generate a fully decked-out list of staff members for
 * the Staff Directory, including inferred user profile links
 * and portrait urls
 */
class Payload
{
    /**
     * Constructor.
     *
     * @param UserMapper     $userMapper
     * @param \External_User $externalUser
     */
    public function __construct(
        private UserMapper $userMapper,
        private \External_User $externalUser
    ) {
    }


    /**
     * Generate a set of staff members but augment with additional info
     *
     * @return array
     * @throws Exception
     */
    public function generate(): array
    {
        // Agglutinate multiple JobPositionDescription
        $staffs = array_reduce(
            $this->externalUser->getStaffContactListExtended(),
            [$this, 'mergeStaffRecords'],
            []
        );

        /* Grab a lookup of existing users, indexed by external_id,
         * who are in a staff user.group_id
         */
        $userLookup = $this->generateUserLookup();

        foreach ($staffs as &$staff) {
            /* Condense into one csv
             */
            $staff['JobPositionDescription'] = implode(', ', $staff['JobPositionDescription']);

            /* This was used for a TKDS custom work (sloane)
             */
            $staff['JobPositionDescriptionAlternate'] = implode(', ', $staff['JobPositionDescriptionAlternate']);

            /* Manufacture a portrait url
             * FIXME You might not need this, because you could infer it on frontend.
             */
            $staff['portrait_url'] = sprintf('/portrait.php?userId=%s&size=square64', $staff['StaffID']);

            /* Figure out a link to profile screen
             */
            $staff['profile_url'] = null;

            $externalId = trim((string)$staff['StaffID']);

            if ('' !== $externalId) {
                $userId = $userLookup[$externalId] ?? null;

                if ($userId) {
                    $staff['profile_url'] = sprintf('/search/user/%s', $userId);
                }
            }

            /**
             * Preferred Name Logic...
             * If both fields exist and contain info (StaffFirstname / StaffPreferredName) use both
             * only if they do not match... (it doesn't really work when you have `Alaress (Alaress)`
             * else use the one that does have content
             */
            $staffPreferredName = $staff['StaffPreferredName'] ?? '';
            $staffPreferredName = trim((string)$staffPreferredName);
            $staffFirstname = $staff['StaffFirstname'] ?? '';
            $staffFirstname = trim((string)$staffFirstname);
            if (('' !== $staffPreferredName && '' !== $staffFirstname) && ($staffFirstname !== $staffPreferredName)) {
                // Combine firstname / preferred name in the standard format
                $staff['StaffFirstname'] = sprintf("%s (%s)", $staffFirstname, $staffPreferredName);
            } elseif ('' !== $staffPreferredName) {
                // Firstname doesn't exist, lets just use / display preferred name
                $staff['StaffFirstname'] = $staffPreferredName;
            }
            // Firstname only exists, so just keep using it...

            /* Provide Preferred Fullname and Formal Fullname, for search plugin reasons
             */
            $staff['fullname'] = trim("{$staff['StaffFirstname']} {$staff['StaffSurname']}");
            $staff['preferredFullname'] = trim("{$staff['StaffPreferredName']} {$staff['StaffSurname']}");
        }

        unset($staff);

        /* Remove lookup index
         */
        return array_values($staffs);
    }


    /**
     * Use this to make a convenient inference about a user id
     * via someone's external id
     *
     * @return array
     * @throws Exception
     */
    private function generateUserLookup(): array
    {
        /* Get a list of people who:
         *   - are not deleted
         *   - have a non-zero synergy_id (externalId) value
         *   - are in the staff group type
         */
        $query = "
            SELECT `user`.synergy_id, `user`.id
            FROM `user`
            INNER JOIN `groups` ON `groups`.id = `user`.group_id
            WHERE `user`.synergy_id IS NOT NULL
            AND `user`.synergy_id != ''
            AND `groups`.group_type = :groupType
            AND `user`.deleted_at IS NULL
        ";

        $statement = $this->userMapper->getDatabase()->executeQuery($query, ['groupType' => Role::STAFF]);

        $lookup = [];

        while ($row = $statement->fetch()) {
            $lookup[$row['synergy_id']] = (int)$row['id'];
        }

        return $lookup;
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
    private function mergeStaffRecords(array $mergedRecords, array $record): array
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
