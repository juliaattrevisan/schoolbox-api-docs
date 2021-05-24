<?php

/**
 * This interface defines how to store documents in a remote SIS.
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector;

use Schoolbox\User;

interface IDocument
{
    /**
     * Retrieve all archived school reports from external SIS for a selected user
     * @param User $student
     * @param array $classificationCodes
     *
     * @return array
     */
    public function getAcademicReportsForStudent(User $student, array $classificationCodes): array;

    /**
     * @param User   $student
     * @param string $documentId
     * @param string $source
     * @param array  $classificationCodes
     */
    public function downloadAcademicReportForStudent(User $student, $documentId, $source, array $classificationCodes);
}
