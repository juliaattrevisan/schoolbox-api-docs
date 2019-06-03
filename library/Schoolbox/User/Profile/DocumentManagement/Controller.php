<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\User\Profile\DocumentManagement;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\External\Connector\IDocument;
use Schoolbox\L10n\RosettaStone;
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
     * @param User $student
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getIndex(User $student): void
    {
        /**
         * Get the valid classification codes. Respect `classification` from query param if it exists.
         * and try to get all the value that matches the value from `external_document_docman_categories`
         *
         * Otherwise, just use the values that's defined in `external_document_docman_categories` config.
         */
        $classificationCodes = $this->splitCategoriesToArray($this->getRegisteredDocmanCategories());

        if ($this->getRequest()->query->get('classification') !== null) {
            $classificationCodes = array_intersect(
                $classificationCodes,
                $this->splitCategoriesToArray($this->getRequest()->query->get('classification'))
            );
        }

        if (count($classificationCodes) < 1) {
            throw new NotFound();
        }

        // And render this thing out
        $reports = $this->externalDocument->getAcademicReportsForStudent($student, $classificationCodes);
        $wysiwyg_value = $this->getConfigLoader()->get('academic_reports_wysiwyg');
        /* @var RosettaStone $l10n */
        $l10n = $this->getDi()->get('l10n');


        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@profileDocumentManagement/index.twig',
                [
                    'student' => $student,
                    'reports' => $reports,
                    'wysiwyg_content' => $wysiwyg_value
                ],
                $l10n->translate('reports',true, true).' for '.$student->formatName()
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
    public function getDownload(User $student, $documentId, $source): void
    {
        $report = $this->externalDocument->downloadAcademicReportForStudent(
            $student,
            $documentId,
            $source,
            $this->splitCategoriesToArray($this->getRegisteredDocmanCategories())
        );

        $headers = $this->getResponse()->headers;
        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'application/pdf');
        $headers->set('Content-Length', $report['filesize']);
        $headers->set('Content-Disposition', 'attachment; filename=' . $report['filename']);

        $this->getResponse()->content->set(
            $this->buildFileOutputFromString($report['blob'])
        );
    }

    /**
     * @return string
     */
    private function getRegisteredDocmanCategories(): string
    {
        return $this->getConfigLoader()->get('external_document_docman_categories');
    }

    /**
     * @param string $categories
     *
     * @return array
     */
    private function splitCategoriesToArray(string $categories): array
    {
        return array_filter(
            array_map(
                'trim',
                explode(',', $categories)
            )
        );
    }
}
