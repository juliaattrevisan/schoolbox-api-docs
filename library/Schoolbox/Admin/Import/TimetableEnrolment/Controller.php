<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\TimetableEnrolment;

use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Import\UserAccount;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Import\TimetableEnrolment
 */
class Controller extends UserAccount\Controller
{

    /**
     * @var string
     */
    protected $basename = 'adminImportTimetableEnrolment';


    /**
     * @var ClassEntryMapper
     */
    protected ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    protected ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param ImporterFactory        $importerFactory
     * @param IConfigLoader          $configLoader
     * @param FilesystemProxy        $filesystemProxy
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     */
    public function __construct(
        ImporterFactory $importerFactory,
        IConfigLoader $configLoader,
        FilesystemProxy $filesystemProxy,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper
    ) {
        parent::__construct($importerFactory, $configLoader, $filesystemProxy);

        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;

        $this->navigation = new SchoolData();
    }


    /**
     * Export the Timetable Class Enrolments back into the csv format
     */
    public function getExport(): void
    {
        $classEntries = $this->classEntryMapper->findAll()->indexBy('id');
        $classInvolvements = $this->classInvolvementMapper->findAll();

        $fp = fopen('php://temp', 'w');

        /* Set up header row
         */
        fputcsv($fp, [
            'Class Code',
            'Class Title',
            'Student Code',
        ]);

        /* Export properties into the same format as the csv
         */
        foreach ($classInvolvements as $classInvolvement) {
            /* Manually match the class involvements to their classes.
             *
             * I don't really think we need to hydrate these normally, because we've never really
             * needed it outside of this one usage.
             *
             * It adds extra hydration that's never used effectively -except- in this one context.
             * Even then we just need it to generate a csv
             */
            $classEntry = $classEntries[$classInvolvement->getClass()->getId()];
            $classInvolvement->setClass($classEntry);

            $row = [
                $classInvolvement->getClass()->getCode(),
                $classInvolvement->getClass()->getName(),
                $classInvolvement->getExternalId()
            ];

            fputcsv($fp, $row);
        }

        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);

        $headers  = $this->getResponse()->headers;

        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', strlen($contents));
        $headers->set('Content-Disposition', "attachment; filename=\"timetableEnrolment.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $contents,
        ]);
    }
}
