<?php

/**
 * Explorer
 *
 * Intended to be used with userProvision.php on the cli
 * Allows you to investigate db tables and their contents
 *
 * Methods nuclear() and split() show you hierarchical nukular
 * families and hierarchical split families contents
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Mfac;

use Alaress\Database\Connector\IConnector;

class Explorer
{

    /**
     * Show the recombined results for a particular family type
     */
    private const NUCLEAR = 'nuclear';
    private const SPLIT = 'split';


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
     * Show recombined nuclear families
     *
     * @param string $query
     * @throws \Alaress\Database\Exception
     */
    public function nuclear(string $query): void
    {
        print_r("\nNuclear Families.\n\n");

        $set = $this->recombine($query);

        print_r($set[self::NUCLEAR]);

        die();
    }


    /**
     * Show recombined split families
     *
     * @param string $query
     * @throws \Alaress\Database\Exception
     */
    public function split(string $query): void
    {
        print_r("\nSplit Families.\n\n");

        $set = $this->recombine($query);

        print_r($set[self::SPLIT]);

        die();
    }


    /**
     * Show split families and their humans
     * Group them up neatly into an identifier so you can see who belongs to who
     *
     * @param string $query
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function recombine(string $query): array
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
                $type      = self::NUCLEAR;
                $rawId     = $row['student_contact_network_login'];
                $studentId = "{$row['student_preferred']} {$row['student_surname']}";
            } else {
                /* Split family
                 */
                $type      = self::SPLIT;
                $rawId     = substr($row['student_contact_id'], 1);
                $studentId = "{$row['student_preferred']} {$row['student_surname']}";
            }

            $stack[$type][$rawId]['contacts'][] = array_intersect_key($row, [
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

        return $stack;
    }
}
