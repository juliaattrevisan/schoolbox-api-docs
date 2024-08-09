#!/usr/bin/php
<?php

/**
 * @author      Josh Cauchi <josh.cauchi@schoolbox.education>
 * @stolenFrom  James Leckie <james.leckie@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Custom\Help\Superstar\Candidacy;
use Custom\Help\Superstar\Payload;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Queries exclusively the Schoolbox Help Database to fetch data required to fulfill OLC KSC.
 * This data is used to populate our engagement database in influxDB and populate the graphs in monitor.schoolbox.com.au
 * Logging is piped to STDERR
 */
$app = new class extends CliTool\App {
    /* Convert unit id to an output field's name for the json payload
     */
    private const LOOKUP_UNIT_PREFIX = [
        137 => 'olc_superuser',
        138 => 'olc_teaching_and_learning',
        139 => 'olc_fundamentals',
        140 => 'olc_extra_curricular',
        141 => 'olc_pastoral_care',
        142 => 'olc_marcomms',
    ];

    private iConnector $db;

    public function run(): void
    {
        /* @var IConnector $db */
        $this->db = $this->getDi()->get('databaseInternalRead');

        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');

        // Only run for Schoolbox Help
        if (strtoupper($config->get(ConfigMapper::NAME_SITE_SCHOOL_CODE)) !== 'HELP') {
            exit;
        }

        /* Get data for the following year (YYYY)
         * TODO: maybe parameterise on the cli with an arg
         */
        $year = date('Y');

        // Fetch all the possible school codes
        $schoolCodes = $this->getSchoolCodes();

        /* Get a list of (school code, counts) tuples
         */
        // $stack = [
        //     'aas' => [
        //         'olc_fundamentals_superstar' => 34,
        //         'olc_fundamentals_star' => 11,
        //     ]
        // ]
        $stack = $this->transpose(
            (new Payload($this->db, $this->getDi()->get('assessmentDataSource'), \Schoolbox\RoleMapper::getInstance()))
                ->generate($year)
        );

        // start with an empty result set
        $result = [];

        // for each of the school codes
        // I need a set of arrays each of which contains the fields: total users, starters, stars, superstars per unit
        foreach ($schoolCodes as $schoolcode) {
            // For each of these school codes
            // I need to run a bunch of queries that only return results on pages that match 'school code + Unit Name'
            $result[$schoolcode]['school_code'] = $schoolcode;

            $result[$schoolcode]['olc_total_users'] = $this->getSchoolUsers($schoolcode);

            $result[$schoolcode]['type'] = $config->get('site_type');

            // and then for each OLC Course unit
            // fetch the number of people in that unit, the amount of 'starters', the amount of 'Stars' and the amount of 'Superstars'
            $unitProvisionedResults = $this->getUnitProvisioned($schoolcode);

            foreach (self::LOOKUP_UNIT_PREFIX as $unitSpecId => $prefix) {
                /* Provisioned data comes from some other place other than $stack
                 * Treat it specially
                 */
                $keyProvisioned = $prefix . '_provisioned';
                $result[$schoolcode][$keyProvisioned] = $this->getCount($unitProvisionedResults, $keyProvisioned) ?? 0;

                /* A list of array keys named after their respective units
                 */
                $keys = [
                    sprintf('%s_%s', $prefix, Candidacy::STARTER),
                    sprintf('%s_%s', $prefix, Candidacy::STAR),
                    sprintf('%s_%s', $prefix, Candidacy::SUPERSTAR),
                ];

                /* A school might not have any superstars/stars/etc
                 * In this case, $stack[$schoolcode] won't exist (no data exists)
                 * Assume a count of zero
                 */
                foreach ($keys as $key) {
                    $result[$schoolcode][$key] = $stack[$schoolcode][$key] ?? 0;
                }
            }
        }

        // output results, ditch the array key which was the school code
        echo json_encode(array_values($result));
    }


    /**
     * Takes a Payload's array, grouped by "guys in a unit" and reorganises its
     * contents into an array that contains counts "grouped by school"
     *
     * @param array $input
     * @return array
     */
    private function transpose(array $input): array
    {
        $stack = [];

        /* input structure is described in Superstar/Payload.php's generate()
         * docblock. This contains a list of superstars and stars across all
         * the schools, grouped by unit spec (but we don't need this struct)
         */

        /* For each unit spec in $olcDataOutput
         */
        foreach ($input as $unitSpecId => $unitData) {
            /* For each of the superstars,
             *     find out what school code they're in
             *     and increment a count for that school code in $stack[$unitSpecId]++
             *
             *  For each of the stars,
             *     find out what school code they're in
             *      and increment a count for that school code in $stack[$unitSpecId]++
             */
            foreach ($unitData['actionables'] as $stardom => $actionableData) {
                foreach ($actionableData as $person) {
                    /* Make everything uppercase lol
                     */
                    $schoolcode = strtoupper($person['school']['code']);

                    /* Define an index to hold our counts separately
                     */
                    $index = sprintf(
                        '%s_%s',
                        self::LOOKUP_UNIT_PREFIX[$unitSpecId],
                        $stardom
                    );

                    /* init stack counter if we never saw this unit before
                     */
                    if (!isset($stack[$schoolcode][$index])) {
                        $stack[$schoolcode][$index] = 0;
                    }

                    /* Add this guy to the count
                     */
                    $stack[$schoolcode][$index]++;
                }
            }
        }

        return $stack;
    }

    /**
     * Fetches a list of school codes based on users that exist
     * if a user doesn't exist with an external id that includes a code
     * then that school won't display in future results
     *
     * @return array
     */
    private function getSchoolCodes(): array
    {
        // Fetch a list of all existing unique School Codes by collecting a unique set of codes taken from the user.synergy_id, before the . delimiter
        $schoolCodeQuery = "
        SELECT
            DISTINCT UPPER(SUBSTRING_INDEX(synergy_id, '.', 1)) as 'school_code'
        FROM
            user u
        WHERE
            deleted_at is NULL
        AND
           synergy_id like '%.%'
        ORDER BY
            school_code";

        $statement = $this->db->executeQuery($schoolCodeQuery);
        return $statement->fetchColumnArray('school_code');
    }

    /**
     * Gets a count of people that exist within each OLC Unit
     * this will inevitably 'break' when a new unit is added
     * as that unit will not show up in grafana
     *
     * @param string $schoolcode
     * @return array
     */
    private function getUnitProvisioned(string $schoolcode): array
    {
        $query = "
            SELECT
                CASE
                    WHEN f.name like '%OLC Extra-Curricular Unit'      THEN 'olc_extra_curricular_provisioned'
                    WHEN f.name like '%OLC Fundamentals Unit'          THEN 'olc_fundamentals_provisioned'
                    WHEN f.name like '%OLC Marcomms Unit'              THEN 'olc_marcomms_provisioned'
                    WHEN f.name like '%OLC Pastoral Care Unit'         THEN 'olc_pastoral_care_provisioned'
                    WHEN f.name like '%OLC Superuser Unit'             THEN 'olc_superuser_provisioned'
                    WHEN f.name like '%OLC Teaching and Learning Unit' THEN 'olc_teaching_and_learning_provisioned'
                END as 'array_field',
                count(ua.uid) as 'num'
            FROM
                folder_code fc
            JOIN
                folder f ON fc.folder_id = f.parent
            JOIN
                userAccess ua ON f.id = ua.sid AND service = '1'
            WHERE
                fc.deleted_at IS NULL
            AND
                f.name REGEXP '[[:<:]]{$schoolcode}[[:>:]]'
            AND
                f.name like '%OLC%'
            GROUP BY
                f.name
            ";

        return $this->db->executeQuery($query)->fetchAll();
    }


    /**
     * Gets a count of all the users that exist for a given school code
     *
     * @param string $schoolcode
     * @return int
     */
    private function getSchoolUsers(string $schoolcode): int
    {
        $query = "
            SELECT
                count(id) as num
            FROM
                user
            WHERE
                deleted_at is NULL
            AND
                synergy_id like '{$schoolcode}.%'
        ";

        return (int)$this->db->executeQuery($query)->fetchColumn(0);
    }


    /**
     * Gets the numerical count of people that are enrolled in a particular OLC unit
     *
     * @param array  $arrayData
     * @param string $field
     * @return int|null
     */
    private function getCount(array $arrayData, string $field): ?int
    {
        // for each element of array data
        foreach ($arrayData as $item) {
            // item {
            //      array_field = olc_extra_curricular_provisioned
            //      num = 6

            // im going to have an array containing num and array_field
            // if array_field matches what I'm looking for return the value of num
            if ($item['array_field'] === $field) {
                return (int)$item['num'];
            }
        }

        return null;
    }
};

// Example output of what the JSON should reflect
//[
//  [
//   'school_code': 'spc',
//   'type': 'prod',
//   'olc_total_school_users: 123,
//   'olc_extra_curricular_provisioned': 123,
//   'olc_extra_currciular_starters': 123,
//  ],
//  [
//   'school_code': 'mggs',
//   'type': 'prod',
//   'olc_total_school_users: 123,
//   'olc_extra_curricular_provisioned': 123,
//   'olc_extra_currciular_starters': 123,
//  ]
//]


$kernel = new CliTool\Kernel($app);
$kernel->runapp();
