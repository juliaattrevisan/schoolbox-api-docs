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
    public function getIndex($student)
    {
        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@profileDocumentManagement/index.twig',
                'data' => array(
                    'student' => $student,
                    'reports' => $this->externalDocument->getAcademicReportsForStudent($student)
                ),
            )
        );
    }

    /**
     * Download the requested file from external document archive
     *
     * @param $student
     * @param $documentId
     */
    public function getDownload($student, $documentId)
    {
        $report = $this->externalDocument->downloadAcademicReportForStudent($student, $documentId);

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
