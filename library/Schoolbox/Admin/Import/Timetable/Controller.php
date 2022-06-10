<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Timetable;

use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Import\UserAccount;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Import\Timetable
 */
class Controller extends UserAccount\Controller
{

    /**
     * @var string
     */
    protected $basename = 'adminImportTimetable';


    /**
     * @var TimetableCycleMapper
     */
    protected TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    protected TimetablePeriodMapper $timetablePeriodMapper;


    /**
     * @var ClassPeriodMapper
     */
    protected ClassPeriodMapper $classPeriodMapper;


    /**
     * @var ClassEntryMapper
     */
    protected ClassEntryMapper $classEntryMapper;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param ImporterFactory       $importerFactory
     * @param IConfigLoader         $configLoader
     * @param FilesystemProxy       $filesystemProxy
     * @param TimetableCycleMapper  $timetableCycleMapper
     * @param TimetablePeriodMapper $timetablePeriodMapper
     * @param ClassPeriodMapper     $classPeriodMapper
     * @param ClassEntryMapper      $classEntryMapper
     */
    public function __construct(
        ImporterFactory $importerFactory,
        IConfigLoader $configLoader,
        FilesystemProxy $filesystemProxy,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassPeriodMapper $classPeriodMapper,
        ClassEntryMapper $classEntryMapper
    ) {
        parent::__construct($importerFactory, $configLoader, $filesystemProxy);

        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->classEntryMapper = $classEntryMapper;

        $this->navigation = new SchoolData();
    }

    /**
     * Export the Timetable Classes back into the csv format
     */
    public function getExport(): void
    {
        $cycles = $this->timetableCycleMapper->findAll()->indexBy('id');
        $periods = $this->timetablePeriodMapper->findAll()->indexBy('id');
        $classEntries = $this->classEntryMapper->findAll()->indexBy('id');
        $classes = $this->classPeriodMapper->findAll();

        $fp = fopen('php://temp', 'w');

        /* Set up header row
         */
        fputcsv($fp, [
            'Term Identifier',
            'Term Title',
            'Period Day',
            'Period Title',
            'Class Code',
            'Class Title',
            'Class Room',
            'Teacher Code'
        ]);

        /* Export properties into the same format as the csv
         */
        foreach ($classes as $class) {
            /* Manually match the periods to their cycles.
             *
             * I don't really think we need to hydrate these normally, because we've never really
             * needed it outside of this one usage.
             *
             * It adds extra hydration that's never used effectively -except- in this one context.
             * Even then we just need it to generate a csv
             */
            $period = $periods[$class->getPeriod()->getId()];
            if ($period === null) {
                continue;
            }

            $period->setCycle($cycles[$class->getPeriod()->getCycle()->getId()]);
            $class->setPeriod($period);

            $classEntry = $classEntries[$class->getClass()->getId()];
            $class->setClass($classEntry);

            $row = [
                $class->getPeriod()->getCycle()->getCycleIdentifier(),
                $class->getPeriod()->getCycle()->getTitle(),
                $class->getPeriod()->getDay(),
                $class->getPeriod()->getTitle(),
                $class->getClass()->getCode(),
                $class->getClass()->getName(),
                $class->getRoom(),
                $class->getTeacher()
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
        $headers->set('Content-Disposition', "attachment; filename=\"timetable.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $contents,
        ]);
    }
}
