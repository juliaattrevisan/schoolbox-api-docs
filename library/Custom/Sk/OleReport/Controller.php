<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Sk\OleReport;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use OutOfBoundsException;
use Schoolbox\User;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Controller for SaintKentigern's OLE Report page.
 */
class Controller extends Base
{
    /**
     * @var IConnector
     */
    private $database;

    /**
     * @var string $schema
     */
    private $schema;

    /**
     * Controller constructor.
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
        $this->schema = $this->retrieveDocumentSchema();
    }


    /**
     * Displays a list of reports for the specified student
     *
     * @api   POST /modules/custom/stk/oleReport/{studentId}
     * @since 160
     *
     * @param User $student
     */
    public function reportList(User $student)
    {
        // No student found
        if (!$student->getId()) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }



        $query = "
            SELECT StudentReportsArchiveLinkSeq, sral.FileYear, sral.FileSemester, Description
            FROM {$this->schema}.StudentReportsArchive sra
            JOIN {$this->schema}.StudentReportsArchiveLink sral ON sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            INNER JOIN dbo.studentyears sy ON sy.id = sral.ID AND sy.fileyear = sral.FileYear
            LEFT OUTER JOIN dbo.uluStudentReports ulusr
            ON sral.fileyear = ulusr.fileyear AND sral.FileSemester = ulusr.FileSemester AND sy.YearLevel = ulusr.YearLevel
            AND sy.StudentCampus =ulusr.Campus
            WHERE
            sral.ID = {$student->getExternalId()} AND
            ulusr.ShowOnOLE =1
            ORDER BY sral.FileYear DESC, sral.FileSemester DESC, sra.CreatedDate DESC
            ";

        $resultIterator = $this->getDatabase()->getIterator($query);

        /**
         * Each report itself is an associative array to enable easy addition of further fields if required.
         *
         * @var array  $reports                      indexed by year
         * @var array  $reports[]                    indexed by semester
         * @var array  $reports[][]                  indexed by reportId
         * @var array  $reports[][][]                associative array of report info.
         * @var string $reports[][][]['description'] description
         */
        $reports = [];
        foreach ($resultIterator as $row) {
            $reportId    = $row['StudentReportsArchiveLinkSeq'];
            $year        = $row['FileYear'];
            $semester    = $row['FileSemester'];
            $description = $row['Description'];

            $reports[$year][$semester][$reportId] = ['description' => $description];
        }

        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customStkOleReport/list.twig',
            'data'     => [
                'student' => $student,
                'reports' => $reports,
            ],
        ]);
    }

    /**
     * Downloads an the specified OLE report for the specified user
     *
     * @api   POST /modules/custom/stk/oleReport/{studentId}/{$reportId}/sendReport
     * @since 160
     *
     * @param User $student
     * @param int $reportId
     */
    public function sendReport(User $student, $reportId)
    {
        // No student found
        if (!$student->getId()) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        // Ensure the report is for the specified user
        $query = "
            SELECT Document, Description
            FROM {$this->schema}.StudentReportsArchive sra
            JOIN {$this->schema}.StudentReportsArchiveLink sral ON sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            WHERE ID = {$student->getExternalId()}
            AND sra.StudentReportsArchiveSeq = {$reportId}";

        $resultIterator = $this->getDatabase()->getIterator($query);

        // Should be a single result
        try {
            $resultIterator->rewind();
        } catch (OutOfBoundsException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
        }
        $row      = $resultIterator->current();
        $content  = $row['Document'];
        $fileName = "{$row['Description']}.pdf";

        $headers  = $this->getResponse()->headers;
        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'application/pdf');
        $headers->set('Content-Length', strlen($content));
        $headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        $this->getResponse()->content->set($this->buildFileOutputFromString($content));
    }

    // Getters / Setters

    /**
     * @return IConnector
     */
    protected function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param IConnector $database
     * @return static
     */
    protected function setDatabase(IConnector $database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * What schema does the document integration use?
     *
     * @return string
     */
    private function retrieveDocumentSchema()
    {
        $externalPing = new \External_Ping();

        if ($externalPing->getVersion() > 67) {
            return "media";
        }

        return "SynergyOneMedia.dbo";
    }
}
