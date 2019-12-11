<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport;

use MSSQL;
use Schoolbox\User;

/**
 * Class Base
 *
 * All report subclasses that need external db connection should
 * inherit this class
 *
 * @package Custom\Diocesan\AchievementReport
 */
abstract class Base
{

    /**
     * Initialised array of term indices
     */
    public const INITIAL_TERMS = [
        1 => null,
        2 => null,
        3 => null,
        4 => null
    ];


    /**
     * MSSQL db handler
     *
     * @var MSSQL
     */
    protected $db;


    /**
     * @var User
     */
    protected $user;


    /**
     * An externalUser array
     *
     * @var array
     */
    protected $externalUser = [];


    /**
     * Table heading
     *
     * @var string
     */
    protected $heading = '';


    /**
     * The year for which to obtain data
     *
     * @var string
     */
    protected $year = 0;


    /**
     * The current year
     *
     * @var string
     */
    protected $currentYear = 0;


    /**
     * All Letter Grades
     *
     * A bunch of letters to obtain
     *
     * @var array
     */
    protected $letterRegistry = ['N', 'A', 'M', 'E'];


    /**
     * Achievement Letter Grades
     *
     * @var array
     */
    protected $achievementLetterRegistry = ['A', 'M', 'E'];


    /**
     * Exclusion Letter Grades
     *
     * These letters mean that the work was not attempted due to
     * absence; no longer available for assessment
     *
     * @var array
     */
    protected $exclusionLetterRegistry = ['Y', 'V', 'Z'];


    /**
     * Constructor
     *
     * @param User  $user
     * @param array $externalUser
     * @param MSSQL $db
     * @param $year
     */
    public function __construct(User $user, array $externalUser, MSSQL $db, $year) {
        $this->user = $user;
        $this->externalUser = $externalUser;
        $this->db = $db;
        $this->year = $year;
        $this->currentYear = date('Y');
    }


    /**
     * Get a list of all the views with 'uv' as a prefix
     */
    protected function debug(): void
    {
        $query = "
            SELECT TABLE_CATALOG,
            TABLE_SCHEMA,
            TABLE_NAME,
            TABLE_TYPE
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_NAME LIKE 'uv%'
            AND TABLE_TYPE = 'VIEW'
        ";

        $stuff = $this->db->getAll($query);

        new \Alaress_Probe($stuff);
    }


    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    abstract public function findData();


    /**
     * Get the primary key out of $externalUser array
     *
     * @return mixed
     */
    protected function getExternalUserId() {
        return $this->externalUser['StudentID'];
    }


    /**
     * Determine if this result is progressive
     * (has square brackets around Result value)
     *
     * @param $result
     * @return bool
     */
    protected function isProgressive($result): bool
    {
        return preg_match('/\[['.implode('', $this->getLetterRegistry()).']\d*\]/', $result);
    }


    /* Getters/Setters
     */

    /**
     * Get heading
     *
     * @return string
     */
    public function getHeading(): string
    {
        return $this->heading;
    }


    /**
     * Get externalUser
     *
     * @return array
     */
    protected function getExternalUser(): array
    {
        return $this->externalUser;
    }


    /**
     * @return string
     */
    protected function getYear(): string
    {
        return $this->year;
    }


    /**
     * Get current year
     *
     * @return string
     */
    public function getCurrentYear(): string
    {
        return $this->currentYear;
    }


    /**
     * Get letter registry
     *
     * @return array
     */
    public function getLetterRegistry(): array
    {
        return $this->letterRegistry;
    }
}
