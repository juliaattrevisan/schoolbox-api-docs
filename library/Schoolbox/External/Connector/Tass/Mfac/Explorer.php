<?php
/**
 * Explorer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Mfac;

use Alaress\Database\Connector\IConnector;
use External_Tass_Helper;


class Explorer
{
    /**
     * @var null|IConnector
     */
    protected $databaseExternal;


    /**
     * The TASS company code to use, for queries which require it.
     *
     * @var string $companyCode
     */
    protected $companyCode;


    /**
     * @var External_Tass_Helper $helper
     */
    protected $helper;


    /**
     * Explorer constructor.
     *
     * @param IConnector $databaseExternal
     * @param string     $companyCode
     */
    public function __construct(IConnector $databaseExternal, string $companyCode)
    {
        $this->databaseExternal = $databaseExternal;
        $this->companyCode = $companyCode;
    }

    /**
     * Find the contents of each table
     *
     * - everything seems to join onto a (par_code, cmpy_code) tuple in one way or another
     * - P = primary parent; S = secondary parent (spouse)
     * - add_num and commtype_code are instance-configurable csv
     * - persondefs is metadata about a specific individual, not a nukular family unit
     * - sfa_num determines whether the parent+spouse is split (null === nukular family, otherwise split)
     *
     * @throws \Alaress\Database\Exception
     */
    public function explorer(): void
    {
        $tables = [
            'parent',
            'paraddress',
            'pia_sfaccess',
            'family',
            'persondefs',
            'student',
            'studcommrules',
        ];

        foreach ($tables as $table) {
            $query = "
                SELECT TOP 1 * 
                FROM {$table}
            ";

            $statement = $this->databaseExternal->prepareQuery($query);
            $statement->execute();

            while ($row = $statement->fetch()) {
                print_r("{$table} ");
                print_r($row);
            }
        }

        die();
    }


    /**
     * Show split families and their humans
     * Group them up neatly
     *
     * @param string $query
     * @throws \Alaress\Database\Exception
     */
    public function recombine(string $query): void
    {
        $statement = $this->databaseExternal->prepareQuery($query);
        $statement->execute([
            'companyCode' => $this->companyCode,
            'companyCode2' => $this->companyCode
        ]);

        $stack = [];

        while ($row = $statement->fetch()) {
            if (!$row['sfa_num']) {

                /* Nuclear family
                 */
                $type      = 'nuclear';
                $rawId     = $row['student_contact_network_login'];
                $contactId = $row['student_contact_id'];
                $studentId = "{$row['student_preferred']} {$row['student_surname']}";
            } else {

                /* Split family
                 */
                $type      = 'split';
                $rawId     = substr($row['student_contact_id'], 1);
                $contactId = $row['student_contact_id'];
                $studentId = "{$row['student_preferred']} {$row['student_surname']}";
            }

            $stack[$type][$rawId]['contacts'][$contactId] = array_intersect_key($row, [
                'sfa_num' => '',
                'student_contact_id' => '',
                'student_contact_network_login' => '',
                'student_contact_title' => '',
                'student_contact_given_name' => '',
                'student_contact_preferred' => '',
                'student_contact_surname' => '',
            ]);

            $stack[$type][$rawId]['students'][$studentId] = array_intersect_key($row, [
                'student_preferred' => '',
                'student_surname' => '',
                'student_year_level' => '',
                'student_campus' => '',
                'student_house' => '',
                'student_gender' => '',
            ]);
        }

        print_r($stack['split']);

        die();
    }
}