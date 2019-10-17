<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Schoolbox\External\Profile;
use Schoolbox\External\Profile\Model;
use Schoolbox\Mapper;

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
     * Export type
     * One of [student, staff, parent, relationship]
     *
     * @var string
     */
    private $type;


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
     * @var Profile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * Exporter constructor.
     *
     * @param string                      $type
     * @param Profile\Mapper              $baseProfileMapper
     * @param Profile\Parent\Mapper       $parentProfileMapper
     * @param Profile\Student\Mapper      $studentProfileMapper
     * @param Profile\Staff\Mapper        $staffProfileMapper
     * @param Profile\Relationship\Mapper $relationshipMapper
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $type,
        Profile\Mapper $baseProfileMapper,
        Profile\Parent\Mapper $parentProfileMapper,
        Profile\Student\Mapper $studentProfileMapper,
        Profile\Staff\Mapper $staffProfileMapper,
        Profile\Relationship\Mapper $relationshipMapper
    ) {
        $this->mapping              = new Mapping($type);
        $this->baseProfileMapper    = $baseProfileMapper;
        $this->parentProfileMapper  = $parentProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->staffProfileMapper   = $staffProfileMapper;
        $this->relationshipMapper   = $relationshipMapper;

        $this->type = $type;
    }


    /**
     * Export based on the $type specified
     */
    public function process(): string
    {
        if ($this->mapping->isStudentProfile($this->type)) {
            return $this->exportStudentProfile();
        } elseif ($this->mapping->isStaffProfile($this->type)) {
            return $this->exportStaffProfile();
        } elseif ($this->mapping->isParentProfile($this->type)) {
            return $this->exportParentProfile();
        } elseif ($this->mapping->isRelationship($this->type)) {
            return $this->exportRelationship();
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
        return $this->exportProfile(Mapping::CSV_STUDENT, Mapping::IMPORT_COLUMNS_STUDENT, $this->studentProfileMapper);
    }


    /**
     * Export csv-formatted data from nested profile structs
     *
     * @return string
     */
    public function exportStaffProfile(): string
    {
        return $this->exportProfile(Mapping::CSV_STAFF, Mapping::IMPORT_COLUMNS_STAFF, $this->staffProfileMapper);
    }


    /**
     * Export csv-formatted data from nested profile structs
     *
     * @return string
     */
    public function exportParentProfile(): string
    {
        return $this->exportProfile(Mapping::CSV_PARENT, Mapping::IMPORT_COLUMNS_PARENT, $this->parentProfileMapper);
    }


    /**
     * Export csv-formatted data from nested profile structs
     *
     * @return string
     */
    public function exportRelationship(): string
    {
        return $this->exportProfile(Mapping::CSV_RELATIONSHIP, Mapping::IMPORT_COLUMNS_RELATIONSHIP, $this->relationshipMapper);
    }


    /**
     * Configurable method for exporting an arbitrary profile
     *
     * @param string $type
     * @param array  $columnSpec
     * @param Mapper $mapper    One of student/staff/parent/relationship mappers
     * @return false|string
     */
    private function exportProfile(string $type, array $columnSpec, Mapper $mapper)
    {

        /* Header row
         */
        $headings = array_keys($columnSpec);

        /* Each csv row follows this template
         */
        $template = array_fill_keys($headings, '');

        /* Header row
         */
        $fp = fopen('php://temp', 'w');
        fputcsv($fp, $headings);

        /* Yank each profile; if there aren't any profiles to load, send back a default csv's contents
         */
        $profiles = $mapper->findAll();

        /* For each profile, flatten and assemble a single row of data
         */
        foreach ($profiles as $profile) {
            $row = $template;

            foreach ($columnSpec as $heading => $column) {
                $method = null;
                $value = '';

                /* Try getting the column value from the extension
                 */
                try {
                    $method = $mapper->getGetMethodName($mapper->getPropertyFromColumn($column));
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

                /* Assign data to $row template
                 */
                if ($value instanceof \Alaress_Date_Format) {

                    /* If it's an ADF, use a db date
                     */
                    $row[$heading] = $value->getDatabaseDate();
                } elseif ($value instanceof Model) {

                    /* If it's a BaseProfile instance, use the external id
                     */
                    $row[$heading] = $value->getExternalId();
                } else {

                    /* Otherwise just use whatever's there
                     */
                    $row[$heading] = $value;
                }
            }

            /* This can get cleared in the iteration, so set this last and in the same format as the import csv
             * Relationship csv has a special meaning for this column so ignore it for that usage
             */
            if ($type !== Mapping::CSV_RELATIONSHIP) {
                $row['Type'] = ucwords($type);
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
}
