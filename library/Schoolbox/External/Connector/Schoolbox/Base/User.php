<?php

/**
 * Schoolbox implementation of user connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Schoolbox\Base;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IUser;
use Schoolbox\External\Normalise\User\Common\Account;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Common\House;
use Schoolbox\External\Normalise\User\Guardian;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\UserRelation;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\User as SchoolboxUser;

class User extends Base implements IUser
{
    // This particular class is a bit of a head-f**k in that this it represents the internal database
    // but is being treated as an external . This creates confusion as to which ID we should be using
    // here.
    //
    // Unfortunately as the application is always passing us the value of 'user.synergy_id' we have to
    // look things up using this external ID (which in Vagrant is from Synergetic) but then within the
    // queries we have to do all internal matching on the 'user.id' field because this is how things are
    // linked within the database.
    //
    // Then lastly we have to make sure that queries will always return the 'user.synergy_id' value to
    // the calling function. As you can imagine this is not ideal- as there is guaranteed to be missing
    // synergy_ids on linked records, but its the best that can be done unless we start passing the
    // 'user.id' value to these functions when Schoolbox is setup as the external database.
    //
    // In this class we also do some GROUP_CONCAT SQL hacks to cram all of the required campus/guardian
    // and other details into a single rows column to avoid having to either iterate multiple rows in
    // PHP, or do multiple external database queries for the purposes of hydration.
    //
    // We obviously need to be able to explode these out to use them, so to avoid conflicts with
    // characters stored in the fields values we use the ASCII character 30 (0x1E) which is a
    // non-printable character specifically designated for the purpose of being a record separator

    /**
     * @inheritdoc
     */
    public function getPortrait(SchoolboxUser $user): ?Storage\Model
    {
        return $user->getPortrait();
    }

    /**
     * @inheritdoc
     */
    public function getGuardians($externalIds, $hydrateStudents = false)
    {
        // Retrieve guardians from the database

        $externalIdBindings = $this->databaseExternal->prepareInBindings($externalIds);

        $guardianIterator = $this->databaseExternal->getQueryIterator("

            SELECT      `user`.`synergy_id` AS `externalId`,
                        `user`.`title` AS `title`,
                        `user`.`firstname` AS `givenName`,
                        `user`.`lastname` AS `lastName`,
                        `user`.`year` AS `year`,
                        `user`.`alt_email` AS `email`,
                        `user`.`username` AS `username`,
                        `user`.`enabled` AS `active`,
                        `user`.`position_title` AS `positionTitle`,
                        1 - `user`.`hide_contacts` AS `visible`,

                        -- This returns a list of the guardians campus IDs sorted by campus ID

                        (
                            SELECT    GROUP_CONCAT(`userCampus`.`campus` SEPARATOR 0x1E)
                            FROM      `userCampus`
                            WHERE     `userCampus`.`uid` = `user`.`id`
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusIds`,

                        -- This returns the campus names for the above IDs (must be sorted the same as above)

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`name` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusNames`,

                        -- This returns the campus external codes for the above IDs (must be sorted the same as above)

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`ext_timetable_code` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusCodes`,

                        -- This returns a list of the students related to this guardian

                        (
                          SELECT      GROUP_CONCAT(`userStudent`.`synergy_id` SEPARATOR 0x1E)
                          FROM        `user_relation`
                          JOIN        `user` as `userStudent` ON `userStudent`.`id` = `user_relation`.`relation_id`
                          WHERE       `user_relation`.`relation_type` = :guardianType AND
                                      `user_relation`.`user_id` = `user`.`id`
                          ORDER BY    `user_relation`.`relation_id`
                        )
                        AS `studentIds`

            FROM        `user`
            JOIN        `groups` ON `groups`.`id` = `user`.`group_id` AND `groups`.`group_type` = :groupType
            WHERE       `user`.`synergy_id` IN ({$externalIdBindings['sql']})
            GROUP BY    `user`.`synergy_id`
        ", array_merge($externalIdBindings['bindings'], [
            'groupType' => Role::PARENT,
            'guardianType' => UserRelation::RELATION_TYPE_PARENT
        ]));

        // Now that we've got the data, work out what to do with it

        if (is_array($externalIds)) {
            // Requested multiple guardians, return results as a collection

            $results = new Collection();

            foreach ($guardianIterator as $guardianRow) {
                $results->add($this->hydrateGuardian($guardianRow, $hydrateStudents));
            }

            return $results;
        } else {
            // Requested a single guardian, return result as a Guardian object

            return $this->hydrateGuardian($guardianIterator->current(), $hydrateStudents);
        }
    }

    /**
     * @inheritdoc
     */
    public function getGuardianDirectory(array $filter = [])
    {
        // Create SQL and bindings for any filters

        $filterSql = [];
        $filterBindings = [];

        foreach ($filter as $field => $value) {
            switch ($field) {
                case 'year':
                    $filterSql[] = '(`user`.`year` LIKE :year)';
                    $filterBindings['year'] = "%{$value}%";

                    break;

                case 'house':
                    $filterSql[] = '(`user`.`house` = :house)';
                    $filterBindings['house'] = $value;

                    break;

                case 'name':
                    $count = 0;
                    $nameSql = '';

                    $nameSplit = explode(' ', $value);

                    foreach ($nameSplit as $name) {
                        if (!empty($nameSql)) {
                            $nameSql .= " OR ";
                        }

                        $nameSql .= "`user`.`firstname` LIKE :firstname{$count} OR `user`.`lastname` LIKE :lastname{$count}";

                        $filterBindings["firstname{$count}"] = "%{$name}%";
                        $filterBindings["lastname{$count}"] = "%{$name}%";

                        $count++;
                    }

                    $filterSql[] = "({$nameSql})";

                    break;

                case 'boarder':
                    $filterSql[] = '(`user`.`boarding_status` LIKE :boardingStatus)';
                    $filterBindings['boardingStatus'] = $value;

                    break;
            }
        }

        $whereSql = '';

        if (count($filterSql) > 0) {
            foreach ($filterSql as $filterSqlCurrent) {
                if (!empty($whereSql)) {
                    $whereSql .= " AND ";
                }

                $whereSql .= $filterSqlCurrent;
            }
        }

        if (!empty($whereSql)) {
            $whereSql = "WHERE {$whereSql}";
        }

        // Create the SQL query

        $studentIterator = $this->databaseExternal->getQueryIterator("

            SELECT      `user`.`synergy_id` as `externalId`,
                        `user`.`title` as `title`,
                        `user`.`firstname` as `firstName`,
                        `user`.`lastname` as `lastName`,
                        `user`.`year` as `year`,
                        `user`.`alt_email` as `email`,
                        `user`.`username` as `username`,
                        `user`.`enabled` as `active`,
                        1 - `user`.`hide_contacts` as `visible`,

                        `house`.`id` AS `houseId`,
                        `house`.`house` AS `houseName`,

                        (
                            SELECT    GROUP_CONCAT(`userCampus`.`campus` SEPARATOR 0x1E)
                            FROM      `userCampus`
                            WHERE     `userCampus`.`uid` = `user`.`id`
                            ORDER BY  `userCampus`.campus
                        )

                        AS `campusIds`,
                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`name` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusNames`,

                        -- This returns the campus external codes for the above IDs (must be sorted the same as above)

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`ext_timetable_code` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusCodes`,

                        (
                          SELECT      GROUP_CONCAT(`userGuardian`.`synergy_id` SEPARATOR 0x1E)
                          FROM        `user_relation`
                          JOIN        `user` as `userGuardian` ON `userGuardian`.`id` = `user_relation`.`user_id`
                          WHERE       `user_relation`.`relation_type` = :guardianType AND
                                      `user_relation`.`relation_id` = `user`.`id`
                          ORDER BY    `user_relation`.`user_id`
                        )
                        AS `guardianIds`

            FROM        `user`
            JOIN        `groups` ON `groups`.`id` = `user`.`group_id` AND `groups`.`group_type` = :groupType
            JOIN        `user_profile` ON `user_id` = `user`.`id`
            LEFT JOIN   `house` ON `house`.`id` = `user`.`house`
            {$whereSql}
            GROUP BY    `user`.`synergy_id`

        ", array_merge($filterBindings, [
            'groupType' => Role::STUDENT,
            'guardianType' => UserRelation::RELATION_TYPE_PARENT

        ]));

        $results = new Collection();

        foreach ($studentIterator as $studentRow) {
            $results->add($this->hydrateStudent($studentRow));
        }

        return $results;
    }

    /**
     * @inheritdoc
     */
    public function getStudents($externalIds, $hydrateGuardians = false)
    {
        $externalIdBindings = $this->databaseExternal->prepareInBindings($externalIds);

        $studentIterator = $this->databaseExternal->getQueryIterator("

            SELECT      `user`.`synergy_id` as `externalId`,
                        `user`.`title` AS `title`,
                        `user`.`firstname` AS `firstName`,
                        `user`.`lastname` AS `lastName`,
                        `user`.`year` AS `year`,
                        `user`.`alt_email` AS `email`,
                        `user`.`username` AS `username`,
                        `user`.`enabled` AS `active`,
                        1 - `user`.`hide_contacts` AS `visible`,

                        `house`.`id` AS `houseId`,
                        `house`.`house` AS `houseName`,

                        (
                            SELECT    GROUP_CONCAT(`userCampus`.`campus` SEPARATOR 0x1E)
                            FROM      `userCampus`
                            WHERE     `userCampus`.`uid` = `user`.`id`
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusIds`,

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`name` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusNames`,

                        -- This returns the campus external codes for the above IDs (must be sorted the same as above)

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`ext_timetable_code` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusCodes`,

                        (
                          SELECT      GROUP_CONCAT(`userGuardian`.`synergy_id` SEPARATOR 0x1E)
                          FROM        `user_relation`
                          JOIN        `user` as `userGuardian` ON `userGuardian`.`id` = `user_relation`.`user_id`
                          WHERE       `user_relation`.`relation_type` = :guardianType AND
                                      `user_relation`.`relation_id` = `user`.`id`
                          ORDER BY    `user_relation`.`user_id`
                        )
                        AS `guardianIds`

            FROM        `user`
            JOIN        `groups` ON `groups`.`id` = `user`.`group_id` AND `groups`.`group_type` = :groupType
            LEFT JOIN   `house` ON `house`.`id` = `user`.`house`
            WHERE       `user`.`synergy_id` IN ({$externalIdBindings['sql']})
            GROUP BY    `user`.`synergy_id`

        ", array_merge($externalIdBindings['bindings'], [
            'groupType' => Role::STUDENT,
            'guardianType' => UserRelation::RELATION_TYPE_PARENT

        ]));

        // Now that we've got the data, work out what to do with it

        if (is_array($externalIds)) {
            // Requested multiple guardians, return results as a collection

            $results = new Collection();

            foreach ($studentIterator as $studentRow) {
                $results->add($this->hydrateStudent($studentRow, $hydrateGuardians));
            }

            return $results;
        } else {
            // Requested a single guardian, return result as a Guardian object

            return $this->hydrateStudent($studentIterator->current(), $hydrateGuardians);
        }
    }

    /**
     * @inheritdoc
     */
    public function getStudentsAccessible($externalId)
    {
        $studentIterator = $this->databaseExternal->getQueryIterator('

            SELECT      `user`.`synergy_id` as `externalId`,
                        `user`.`title` AS `title`,
                        `user`.`firstname` AS `firstName`,
                        `user`.`lastname` AS `lastName`,
                        `user`.`year` AS `year`,
                        `user`.`alt_email` AS `email`,
                        `user`.`username` AS `username`,
                        `user`.`enabled` AS `active`,
                        1 - `user`.`hide_contacts` AS `visible`,

                        `house`.`id` AS `houseId`,
                        `house`.`house` AS `houseName`,

                        (
                            SELECT    GROUP_CONCAT(`userCampus`.`campus` SEPARATOR 0x1E)
                            FROM      `userCampus`
                            WHERE     `userCampus`.`uid` = `user`.`id`
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusIds`,

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`name` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusNames`,

                        -- This returns the campus external codes for the above IDs (must be sorted the same as above)

                        (
                            SELECT    GROUP_CONCAT(`luCampus`.`ext_timetable_code` SEPARATOR 0x1E)
                            FROM      `luCampus`
                            JOIN      `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE     `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY  `userCampus`.campus
                        )
                        AS `campusCodes`,

                        (
                          SELECT      GROUP_CONCAT(`userGuardian`.`synergy_id` SEPARATOR 0x1E)
                          FROM        `user_relation`
                          JOIN        `user` as `userGuardian` ON `userGuardian`.`id` = `user_relation`.`user_id`
                          WHERE       `user_relation`.`relation_type` = :guardianType AND
                                      `user_relation`.`relation_id` = `user`.`id`
                          ORDER BY    `user_relation`.`user_id`
                        )
                        AS `guardianIds`

            FROM        `user`
            JOIN        `groups` ON `groups`.`id` = `user`.`group_id` AND `groups`.`group_type` = :groupType
            LEFT JOIN   `house` ON `house`.`id` = `user`.`house`
            WHERE       `user`.`id` IN (
                SELECT    `user_id`
                FROM      `user_relation`
                WHERE     `relation_id` = (
                    SELECT    `userGuardian`.`id`
                    FROM      `user` AS `userGuardian`
                    WHERE     `userGuardian`.`synergy_id` = :externalId
                )
            )
            GROUP BY    `user`.`id`

        ', [

            'externalId' => $externalId,
            'groupType' => Role::STUDENT,
            'guardianType' => UserRelation::RELATION_TYPE_PARENT
        ]);

        $results = new Collection();

        foreach ($studentIterator as $student) {
            $results->add($this->hydrateStudent($student));
        }

        return $results;
    }

    /**
     * @inheritdoc
     */
    public function getStaff($externalIds)
    {
        // Retrieve staff from the database

        $externalIdBindings = $this->databaseExternal->prepareInBindings($externalIds);

        $staffIterator = $this->databaseExternal->getQueryIterator("

            SELECT      `user`.`synergy_id` AS `externalId`,
                        `user`.`title` AS `title`,
                        `user`.`firstname` AS `firstName`,
                        `user`.`lastname` AS `lastName`,
                        `user`.`year` AS `year`,
                        `user`.`alt_email` AS `email`,
                        `user`.`username` AS `username`,
                        `user`.`enabled` AS `active`,
                        `user`.`position_title` AS `positionTitle`,
                        1 - `user`.`hide_contacts` AS `visible`,

                        `house`.`id` AS `houseId`,
                        `house`.`house` AS `houseName`,

                        (
                            SELECT      GROUP_CONCAT(`userCampus`.`campus` SEPARATOR 0x1E)
                            FROM        `userCampus`
                            WHERE       `userCampus`.`uid` = `user`.`id`
                            ORDER BY    `userCampus`.campus
                        )
                        AS `campusIds`,

                        (
                            SELECT      GROUP_CONCAT(`luCampus`.`name` SEPARATOR 0x1E)
                            FROM        `luCampus`
                            JOIN        `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE       `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY    `userCampus`.campus
                        )
                        AS `campusNames`

            FROM        `user`
            JOIN        `groups` ON `groups`.`id` = `user`.`group_id` AND `groups`.`group_type` = :groupType
            LEFT JOIN   `house` ON `house`.`id` = `user`.`house`
            WHERE       `user`.`synergy_id` IN ({$externalIdBindings['sql']})
            GROUP BY    `user`.`synergy_id`

        ", array_merge($externalIdBindings['bindings'], [
            'groupType' => Role::STAFF
        ]));


        // Now that we've got the data, work out what to do with it

        if (is_array($externalIds)) {
            // Requested multiple staff, return results as a collection

            $results = new Collection();

            foreach ($staffIterator as $staffRow) {
                $results->add($this->hydrateStaff($staffRow));
            }

            return $results;
        } else {
            // Requested a single staff, return result as a Staff object

            return $this->hydrateStaff($staffIterator->current());
        }
    }

    /**
     * @inheritdoc
     */
    public function getStaffDirectory(array $filter = [])
    {
        $staffIterator = $this->databaseExternal->getQueryIterator('

            SELECT      `user`.`synergy_id` AS `externalId`,
                        `user`.`title` AS `title`,
                        `user`.`firstname` AS `firstName`,
                        `user`.`lastname` AS `lastName`,
                        `user`.`year` AS `year`,
                        `user`.`alt_email` AS `email`,
                        `user`.`username` AS `username`,
                        `user`.`enabled` AS `active`,
                        `user`.`position_title` AS `positionTitle`,
                        1 - `user`.`hide_contacts` AS `visible`,

                        `house`.`id` AS `houseId`,
                        `house`.`house` AS `houseName`,

                        (
                            SELECT      GROUP_CONCAT(`userCampus`.`campus` SEPARATOR 0x1E)
                            FROM        `userCampus`
                            WHERE       `userCampus`.`uid` = `user`.`id`
                            ORDER BY    `userCampus`.campus
                        )
                        AS `campusIds`,

                        (
                            SELECT      GROUP_CONCAT(`luCampus`.`name` SEPARATOR 0x1E)
                            FROM        `luCampus`
                            JOIN        `userCampus` ON `userCampus`.`campus` = `luCampus`.`id`
                            WHERE       `userCampus`.`uid` = `user`.`id` AND `luCampus`.`deleted_at` IS NULL
                            ORDER BY    `userCampus`.campus
                        )
                        AS `campusNames`

            FROM        `user`
            JOIN        `groups` ON `groups`.`id` = `user`.`group_id` AND `groups`.`group_type` = :groupType
            LEFT JOIN   `house` ON `house`.`id` = `user`.`house`
            GROUP BY    `user`.`synergy_id`

        ', [
            'groupType' => Role::STAFF
        ]);

        $results = new Collection();

        foreach ($staffIterator as $staffRow) {
            $results->add($this->hydrateStaff($staffRow));
        }

        return $results;
    }

    /**
     * @inheritdoc
     */
    public function getTutors($externalIds)
    {
        // There is no such concept in the Schoolbox database, return an empty collection or false
        // depending on what was requested

        if (is_array($externalIds)) {
            return new Collection();
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function getEmails($externalIds): array
    {
        // Get all email addresses from the specified external IDs

        $externalIdBindings = $this->databaseExternal->prepareInBindings($externalIds);

        $statement = $this->databaseExternal->executeQuery(
            "
                SELECT      CONCAT(lastname, ', ' , firstname) AS `name`,
                            `alt_email` as `email`
                FROM        `user`
                WHERE       `synergy_id` IN ({$externalIdBindings['sql']})
            ",
            $externalIdBindings['bindings']
        );

        $addresses = $statement->fetchColumnArray('email', 'name');

        // Sanitize and validate the email addresses before adding them to the collection

        foreach ($addresses as $name => $email) {
            $addresses[$name] = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (!filter_var($addresses[$name], FILTER_VALIDATE_EMAIL)) {
                unset($addresses[$name]);
            }
        }

        return $addresses;
    }

    /**
     * @inheritdoc
     */
    public function getCampusInvolvement($externalIds)
    {
        $externalIdBindings = $this->databaseExternal->prepareInBindings($externalIds);

        $campusIterator = $this->databaseExternal->getQueryIterator(
            "
                SELECT DISTINCT
                          `luCampus`.`id` as `id`,
                          `luCampus`.`ext_timetable_code` as `code`,
                          `luCampus`.`name` as `name`
                FROM      `luCampus`
                JOIN      `userCampus` ON `userCampus`.`uid` IN
                (
                    SELECT    `user`.`id`
                    FROM      `user`
                    WHERE     `user`.`synergy_id` IN ({$externalIdBindings['sql']}) AND `luCampus`.`deleted_at` IS NULL
                )
                GROUP BY  `luCampus`.`id`
            ",
            $externalIdBindings['bindings']
        );

        $results = new Collection();

        foreach ($campusIterator as $campusRow) {
            $campus = new Campus();
            $campus->setId($campusRow['id']);
            $campus->setCode($campusRow['code']);
            $campus->setName($campusRow['name']);
            $results->add($campus);
        }

        return $results;
    }

    /**
     * This is an internal helper function that takes the result of a student query
     * and returns a hydrates NDS student object
     *
     * @param array $studentRow
     * @param bool $hydrateGuardians Flag indicating if we should hydrate any guardians linked to the student
     * @return Student
     */
    private function hydrateStudent($studentRow, $hydrateGuardians = false)
    {
        // Now that we've got the data, put it into an NDS student object

        $student = new Student();

        $student->setId($studentRow['externalId']);
        $student->setTitle($studentRow['title']);
        $student->setGivenName($studentRow['firstName']);
        $student->setPreferredName($studentRow['firstName']);
        $student->setLastname($studentRow['lastName']);
        $student->setEmail($studentRow['email']);
        $student->setYear($studentRow['year']);

        // Campus Involvement

        $campusIds = explode(chr(0x1E), $studentRow['campusIds']);
        $campusNames = explode(chr(0x1E), $studentRow['campusNames']);
        $campusCodes = explode(chr(0x1E), $studentRow['campusCodes']);

        if ($studentRow['campusIds'] !== '') {
            $campusCollection = new Collection();

            for ($i = 0; $i < count($campusIds); $i++) {
                $campus = new Campus();
                $campus->setId($campusIds[$i]);
                $campus->setCode($campusCodes[$i]);
                $campus->setName($campusNames[$i]);
                $campusCollection->add($campus);
            }

            $student->setCampus($campusCollection);
        }

        // Account

        $account = new Account();
        $account->setActive($studentRow['active']);
        $account->setLogin($studentRow['username']);
        $student->setAccount($account);

        // Guardians

        $guardianIds = explode(chr(0x1E), $studentRow['guardianIds']);

        if ($studentRow['guardianIds'] !== '') {
            $guardianCollection = new Collection();

            for ($i = 0; $i < count($guardianIds); $i++) {
                if ($hydrateGuardians) {
                    // Return hydrated guardian record

                    $guardian = $this->getGuardians($guardianIds[$i]);
                } else {
                    // Just return a blank guardian record with their ID

                    $guardian = new Guardian();
                    $guardian->setId($guardianIds[$i]);
                }

                $guardianCollection->add($guardian);
            }

            $student->setGuardians($guardianCollection);
        }

        if (!empty($studentRow['houseId'])) {
            $house = new House();
            $house->setId($studentRow['houseId']);
            $house->setName($studentRow['houseName']);
            $house->setCode(null);
            $house->setHousemaster(null);
            $student->setHouse($house);
        }

        $student->setPubliclyVisible($studentRow['visible']);

        // The following fields are currently unsupported by the Schoolbox database

        $student->setAlert(null);
        $student->setBoardingHouse(null);
        $student->setForm(null);
        $student->setGender(null);
        $student->setGuardiansSeparated(null);
        $student->setHomeEmail(null);
        $student->setLocker(null);
        $student->setMedical(null);
        $student->setPhoneHome(null);
        $student->setPhoneMobile(null);
        $student->setSpecialNeeds(null);
        $student->setTutor(null);
        $student->setWorkEmail(null);

        return $student;
    }

    /**
     * This is an internal helper function that takes the result of a staff query
     * and returns a hydrated NDS staff object
     *
     * @param array $staffRow
     * @return Staff
     */
    private function hydrateStaff($staffRow)
    {
        // Now that we've got the data, put it into an NDS staff object

        $staff = new Staff();
        $staff->setId($staffRow['externalId']);
        $staff->setTitle($staffRow['title']);
        $staff->setGivenName($staffRow['firstName']);
        $staff->setPreferredName($staffRow['firstName']);
        $staff->setLastname($staffRow['lastName']);
        $staff->setEmail($staffRow['email']);
        $staff->setWorkEmail($staffRow['email']);
        $staff->setYear($staffRow['year']);
        $staff->setPubliclyVisible($staffRow['visible']);

        // Campus Involvement

        $campusIds = explode(chr(0x1E), $staffRow['campusIds']);
        $campusNames = explode(chr(0x1E), $staffRow['campusNames']);
        $campusCodes = explode(chr(0x1E), $staffRow['campusCodes']);

        if ($staffRow['campusIds'] !== '') {
            $campusCollection = new Collection();

            for ($i = 0; $i < count($campusIds); $i++) {
                $campus = new Campus();
                $campus->setId($campusIds[$i]);
                $campus->setCode($campusCodes[$i]);
                $campus->setName($campusNames[$i]);
                $campusCollection->add($campus);
            }

            $staff->setCampus($campusCollection);
        }

        // Account

        $account = new Account();
        $account->setActive($staffRow['active']);
        $account->setLogin($staffRow['username']);
        $staff->setAccount($account);

        if (!empty($staffRow['houseId'])) {
            $house = new House();
            $house->setId($staffRow['houseId']);
            $house->setName($staffRow['houseName']);
            $house->setCode(null);
            $house->setHousemaster(null);
            $staff->setHouse($house);
        }

        // Metadata

        $meta      = new Staff\Meta();
        $positions = new GenericCollection(Staff\Position::class);
        $positions->add((new Staff\Position())->setDescription($staffRow['positionTitle']));
        $meta->setPosition($positions);
        $staff->setMeta($meta);

        // The following fields are currently unsupported by the Schoolbox database

        $staff->setBoardingHouse(null);
        $staff->setGender(null);
        $staff->setHomeEmail(null);
        $staff->setPhoneHome(null);
        $staff->setPhoneMobile(null);

        return $staff;
    }

    /**
     * This is an internal helper function that takes the result of a guardian query
     * and returns a hydrates NDS guardian object
     *
     * @param array $guardianRow Result of guardian query
     * @param bool $hydrateStudents If set, the guardians linked student records will also be retrieved and hydrated
     * @return Guardian
     */
    private function hydrateGuardian($guardianRow, $hydrateStudents = false)
    {
        $guardian = new Guardian();

        $guardian->setId($guardianRow['externalId']);
        $guardian->setTitle($guardianRow['title']);
        $guardian->setGivenName($guardianRow['givenName']);
        $guardian->setPreferredName($guardianRow['givenName']);
        $guardian->setLastname($guardianRow['lastName']);
        $guardian->setEmail($guardianRow['email']);
        $guardian->setPubliclyVisible($guardianRow['visible']);
        $guardian->setTitle($guardianRow['positionTitle']);

        // Campus Involvement

        $campusIds = explode(chr(0x1E), $guardianRow['campusIds']);
        $campusNames = explode(chr(0x1E), $guardianRow['campusNames']);
        $campusCodes = explode(chr(0x1E), $guardianRow['campusCodes']);

        if ($guardianRow['campusIds'] !== '') {
            $campusCollection = new Collection();

            for ($i = 0; $i < count($campusIds); $i++) {
                $campus = new Campus();
                $campus->setId($campusIds[$i]);
                $campus->setCode($campusCodes[$i]);
                $campus->setName($campusNames[$i]);
                $campusCollection->add($campus);
            }

            $guardian->setCampus($campusCollection);
        }

        // Account

        $account = new Account();
        $account->setActive($guardianRow['active']);
        $account->setLogin($guardianRow['username']);
        $guardian->setAccount($account);

        // Students

        $studentIds = explode(chr(0x1E), $guardianRow['studentIds']);

        if ($guardianRow['studentIds'] !== '') {
            $studentCollection = new Collection();

            for ($i = 0; $i < count($studentIds); $i++) {
                // Check if we wanted the student records hydrated

                if ($hydrateStudents) {
                    $student = $this->getStudents($studentIds[$i]);
                } else {
                    // We didn't want a fully hydrated student, just return a blank student with the external ID

                    $student = new Student();
                    $student->setId($studentIds[$i]);
                }

                $studentCollection->add($student);
            }

            $guardian->setStudents($studentCollection);
        }

        // The following fields are currently supported properly by the Schoolbox database. Some of
        // this information has been hacked into the `user_profile` table, but there is not enough
        // information to link this stuff in any useful way.

        $guardian->setBoardingHouse(null);
        $guardian->setGender(null);
        $guardian->setHomeEmail(null);
        $guardian->setPhoneHome(null);
        $guardian->setPhoneMobile(null);

        return $guardian;
    }
}
