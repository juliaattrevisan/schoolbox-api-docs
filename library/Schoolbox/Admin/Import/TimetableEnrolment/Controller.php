<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\TimetableEnrolment;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Import\UserAccount;
use Schoolbox\External\ClassEntryMapper;
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
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param ImporterFactory $importerFactory
     * @param IConfigLoader $configLoader
     * @param FilesystemProxy $filesystemProxy
     * @param IConnector $database
     */
    public function __construct(
        ImporterFactory $importerFactory,
        IConfigLoader $configLoader,
        FilesystemProxy $filesystemProxy,
        private IConnector $database
    ) {
        parent::__construct($importerFactory, $configLoader, $filesystemProxy);

        $this->navigation = new SchoolData();
    }


    /**
     * Export the Timetable Class Enrolments back into the csv format
     */
    public function getExport(): void
    {
        /* Use a custom query to generate relationships
         * Note that this excludes any broken involvements where it references
         * nonexisting classes; use a LEFT JOIN instead, to reveal those.
         */
        $query = "
            SELECT
                sc.code,
                sc.name,
                scu.external_id
            FROM
                sis_class_user scu INNER JOIN sis_class sc ON scu.class_id = sc.id
        ";

        $statement = $this->database->query($query);
        $statement->execute();

        $fp = fopen('php://temp', 'w');

        /* Set up header row
         */
        fputcsv($fp, [
            'Class Code',
            'Class Title',
            'Student Code',
        ], escape: '');

        /* Export properties into the same format as the csv
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($fp, $row, escape: '');
        }

        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);

        $this->response = $this->responseFromContent($contents)
            ->withHeader('Pragma', ' ')
            ->withHeader('Cache-Control', ' ')
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Length', strlen($contents))
            ->withHeader('Content-Disposition', "attachment; filename=\"timetableEnrolment.csv\"")
        ;
    }
}
