<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);


/**
 * Class Explorer
 *
 * Lets you explore the Doublefirst db table names and sample row
 * so you can ctrl-f your way around when you're looking for a
 * specific column or value
 *
<?php
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$explorer = new External_Doublefirst_Explorer(new External_User());
$explorer->index();

 *
 * @package Schoolbox\External\Doublefirst
 */
class External_Doublefirst_Explorer
{

    /**
     * @var External_User
     */
    private $externalUser;


    /**
     * @var MSSQL
     */
    private $db;


    /**
     * Explorer constructor.
     * @param External_User $externalUser
     */
    public function __construct(External_User $externalUser)
    {
        $this->externalUser = $externalUser;
        $this->db = $externalUser->getDatabase()->getDatabase();
    }


    /**
     * Get a list of available tables and one row of sample data
     */
    public function index(): void
    {
        $query = '
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
        ';

        $tables = array_column($this->db->getAll($query), 'TABLE_NAME');

        sort($tables);

        foreach ($tables as $table) {
            try {
                $row = $this->db->getAll("
                    SELECT TOP 1 *
                    FROM {$table}
                ");
            } catch (\Alaress\Database\Exception $e) {
                new \Alaress_Probe($table, $e->getMessage());
            }

            /* By default find the first row, but you can get a specific
             * element here if you need
             */
            $example = $row[0] ?? [];

            /* Special cases (contains binary or other shenanigans)
             */
            if ($table === 'export_PupilPhoto') {
                $example['Photo'] = '(contains binary)';
            } elseif ($table === 'export_StaffPhoto') {
                $example['StaffPhoto'] = '(contains binary)';
            } elseif ($table === 'export_PupilSEN') {
                $example['Photo'] = '(contains binary)';
            } elseif ($table === 'SchoolInformation') {
                $example['SchoolLogo'] = '(contains binary)';
            } elseif ($table === 'StaffPersonalDetails') {
                $example['StaffPhoto'] = '(contains binary)';
            }

            new \Alaress_Probe($table, $example);
        }
    }
}
