<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\TimetableStructure;

use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Import\UserAccount;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Import\ImporterFactory;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Import\TimetableStructure
 */
class Controller extends UserAccount\Controller
{

    /**
     * @var string
     */
    protected $basename = 'adminImportTimetableStructure';


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param ImporterFactory $importerFactory
     * @param IConfigLoader   $configLoader
     */
    public function __construct(ImporterFactory $importerFactory, IConfigLoader $configLoader)
    {
        parent::__construct($importerFactory, $configLoader);

        $this->navigation = new SchoolData();
    }


    /**
     * Export the Timetable Definition back into the csv format
     */
    public function getExport(): void
    {

        /* TODO Inject these as dependencies
         */
        $cycleMapper = TimetableCycleMapper::getInstance();
        $periodMapper = TimetablePeriodMapper::getInstance();

        $cycles = $cycleMapper->findAll()->indexBy('id');
        $periods = $periodMapper->findAll();

        $fp = fopen('php://temp', 'w');

        /* Export properties into the same format as the csv
         */
        foreach ($periods as $period) {

            /* Manually match the periods to their cycles.
             *
             * I don't really think we need to hydrate cycles normally, because we've never really
             * needed it outside of this one usage.
             *
             * It adds extra hydration that's never used effectively -except- in this one context.
             * Even then we just need it to generate a csv; we don't obtain cycle data via a period
             * in most (if not all) usages thus far
             */
            $period->setCycle($cycles[$period->getCycle()->getId()]);

            $row = [
                $period->getCycle()->getCycleIdentifier(),
                $period->getCycle()->getTitle(),
                $period->getCycle()->getStart()->getDatabaseDate(),
                $period->getCycle()->getFinish()->getDatabaseDate(),
                $period->getCycle()->getStartDay(),
                $period->getDay(),
                $period->getTitle(),
                $period->getStart()->getDatabaseTime(),
                $period->getFinish()->getDatabaseTime()
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
        $headers->set('Content-Disposition', "attachment; filename=\"timetableStructure.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $contents,
        ]);
    }
}
