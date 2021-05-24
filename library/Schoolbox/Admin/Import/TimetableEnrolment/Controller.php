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
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     */
    public function __construct(
        ImporterFactory $importerFactory,
        IConfigLoader $configLoader,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper
    ) {
        parent::__construct($importerFactory, $configLoader);

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

        foreach ($classInvolvements as $classInvolvement) {
        }
    }
}
