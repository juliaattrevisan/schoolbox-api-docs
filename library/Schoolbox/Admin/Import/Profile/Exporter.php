<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Profile;

/**
 * Class Exporter
 *
 * Export data currently in sis_user_profile_* into the requested CSV format
 *
 * The way it comes out should be structured identically to the way it went in,
 * for the sake of familiarity for end users
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Exporter
{

    /**
     * @var Mapping
     */
    private $mapping;


    /**
     * @var Profile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var Profile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var Profile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var Profile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var Profile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * @var IConnector
     */
    private $database;


    /**
     * An array of log messages
     *
     * @var array
     */
    private $log = [];


    /**
     * Exporter constructor.
     *
     * @param string               $type
     * @param IConnector           $database
     * @param LoggerInterface|null $logger
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type, IConnector $database, LoggerInterface $logger = null)
    {
        $this->mapping = new Mapping($type);
        $this->baseProfileMapper = new Profile\Mapper();
        $this->parentProfileMapper = new Profile\Parent\Mapper();
        $this->studentProfileMapper = new Profile\Student\Mapper();
        $this->staffProfileMapper = new Profile\Staff\Mapper();
        $this->relationMapper = new Profile\Relation\Mapper();
        $this->database = $database;
        $this->logger = $logger;

        $this->type = $type;
    }


    /**
     * Export based on the $type specified
     *
     * @throws \InvalidArgumentException
     */
    public function process(): string
    {
        return $this->processOne();
    }


    /**
     * Export one of the csv types
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function processOne(): string
    {
        if ($this->mapping->isStudentProfile($this->type)) {
            return $this->exportStudentProfile();
        } elseif ($this->mapping->isStaffProfile($this->type)) {
            return $this->exportStaffProfile();
        } elseif ($this->mapping->isParentProfile($this->type)) {
            return $this->exportParentProfile();
        } elseif ($this->mapping->isRelation($this->type)) {
            return $this->exportRelation();
        }

        return '';
    }


    /**
     * Export csv-formatted data from nested profile structs
     *
     * @return string
     */
    public function exportStudentProfile(): string
    {

        /* Header row
         */
        $headings = array_keys(Mapping::IMPORT_COLUMNS_STUDENT);

        /* Each csv row follows this template
         */
        $template = array_fill_keys($headings, '');
        $template['Type'] = Mapping::CSV_STUDENT;

        /* Header row
         */
        $fp = fopen('php://temp', 'w');
        fputcsv($fp, $headings);

        /* Yank each profile
         */
        $profiles = $this->studentProfileMapper->findAll();

        /* For each profile, flatten and assemble a single row of data
         */
        foreach ($profiles as $profile) {
            $row = $template;

            foreach (Mapping::IMPORT_COLUMNS_STUDENT as $heading => $column) {
                $method = null;
                $value = '';

                /* Try getting the column value from the extension
                 */
                try {
                    $method = $this->studentProfileMapper->getGetMethodName($this->studentProfileMapper->getPropertyFromColumn($column));
                    $value = $profile->$method();
                } catch (\InvalidArgumentException $e) {
                    // noop: set as null
                }

                /* If it isn't there, try looking in the base profile
                 */
                try {
                    $method = $this->baseProfileMapper->getGetMethodName($this->baseProfileMapper->getPropertyFromColumn($column));
                    $value = $profile->getBaseProfile()->$method();
                } catch (\InvalidArgumentException $e) {
                    // noop: set as null
                }

                /* If it's an ADF, use a db date
                 */
                if ($value instanceof \Alaress_Date_Format) {
                    $row[$heading] = $value->getDatabaseDate();
                } else {
                    $row[$heading] = $value;
                }
            }

            /* Values only, comma separated
             */
            fputcsv($fp, array_values($row));
        }

        rewind($fp);

        $contents = stream_get_contents($fp);

        fclose($fp);

        return $contents;
    }


    public function exportStaffProfile()
    {
        return '';
    }

    public function exportParentProfile()
    {
        return '';
    }

    public function exportRelation()
    {
        return '';
    }
}
