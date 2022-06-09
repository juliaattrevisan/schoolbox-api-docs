<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Csv\Reader;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer;
use Schoolbox\Resource\Storage;

/**
 * Class ImporterFactory
 *
 * Spawn one of the strategies that imports data
 *
 * @package Schoolbox\Import
 */
class ImporterFactory
{

    public const IMPORT_USER = 'adminImportUserAccount';

    public const IMPORT_STRUCTURE = 'adminImportTimetableStructure';

    public const IMPORT_TIMETABLE = 'adminImportTimetable';

    public const IMPORT_ENROLMENT = 'adminImportTimetableEnrolment';


    /**
     * Builds an importer object based on this subcontroller's basename.
     *
     * @param string          $basename
     * @param Reader          $reader
     * @param LoggerInterface $log
     * @param bool            $performUpdate
     *
     * @return Importer\AbstractImporter
     * @throws \OutOfRangeException
     */
    public function buildImporter(string $basename, Reader $reader, LoggerInterface $log, $performUpdate = false): ?\Schoolbox\External\Importer\AbstractImporter
    {
        switch ($basename) {
            case self::IMPORT_USER:
                return new Importer\User($reader, $log, $performUpdate);
            case self::IMPORT_STRUCTURE:
                return new Importer\TimetableStructure($reader, $log, $performUpdate);
            case self::IMPORT_TIMETABLE:
                return new Importer\Timetable($reader, $log, $performUpdate);
            case self::IMPORT_ENROLMENT:
                return new Importer\TimetableEnrolment($reader, $log, $performUpdate);
            default:
                throw new \OutOfRangeException(sprintf('No importer matches basename "%s"', $basename));
        }
    }


    /**
     * Builds a CSV reader out of the uploaded file.
     *
     * @param string          $basename
     * @param string|resource $upload
     * @return Reader
     * @throws \RuntimeException
     */
    public function buildReader(string $basename, $upload): \Alaress\Csv\Reader
    {
        $reader = new Reader($upload);
        $reader->setLoadFromFirst(true);

        // User import does not fill down - the others do
        switch ($basename) {
            case self::IMPORT_STRUCTURE:
            case self::IMPORT_TIMETABLE:
            case self::IMPORT_ENROLMENT:
                $reader->setFillDown(true);
                break;

            case self::IMPORT_USER:
            default:
                break;
        }

        return $reader;
    }
}
