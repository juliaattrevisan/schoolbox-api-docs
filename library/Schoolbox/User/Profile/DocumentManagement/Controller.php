<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\User\Profile\DocumentManagement;

use Alaress\Web\Controller\Base;
use Schoolbox\External\Connector\IDocument;
use Schoolbox\User;

class Controller extends Base
{
    /** @var IDocument $externalDocument */
    private $externalDocument;

    /**
     * Constructor
     *
     * @param IDocument $externalDocument
     */
    public function __construct(IDocument $externalDocument)
    {
        $this->externalDocument = $externalDocument;
    }

    /**
     * Display all documents for selected student
     *
     * @param User $student
     */
    public function getIndex(User $student)
    {
        // And render this thing out
        $reports = $this->externalDocument->getAcademicReportsForStudent($student);
        $wysiwyg_value = $this->getConfigLoader()->get('academic_reports_wysiwyg');

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@profileDocumentManagement/index.twig',
                [
                    'student' => $student,
                    'reports' => $reports,
                    'wysiwyg_content' => $wysiwyg_value
                ],
                'Academic Reports for '.$student->formatName()
            )
        );
    }

    /**
     * Download the requested file from external document archive
     *
     * @param User   $student
     * @param string $documentId
     * @param string $source
     */
    public function getDownload(User $student, $documentId, $source)
    {
        $report = $this->externalDocument->downloadAcademicReportForStudent($student, $documentId, $source);

        $headers = $this->getResponse()->headers;
        $headers->set("Pragma", " ");
        $headers->set("Cache-Control", " ");
        $headers->set("Content-Type", "application/pdf");
        $headers->set("Content-Length", $report["filesize"]);
        $headers->set("Content-Disposition", "attachment; filename=\"{$report["filename"]}\"");

        $this->getResponse()->content->set(
            $this->buildFileOutputFromString($report['blob'])
        );
    }
}
