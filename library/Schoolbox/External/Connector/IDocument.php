<?php
/**
 * This interface defines how to store documents in a remote SIS.
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector;

use Schoolbox\User;

interface IDocument
{
    /**
     * Retrieve all archived school reports from external SIS for a selected user
     *
     * @param User $student
     * @return array
     */
    public function getAcademicReportsForStudent(User $student);

    /**
     * Returns an array containing the academic reports binary contents, length, and filename
     *
     * @param User $student
     * @param string $documentId
     * @param string $source
     * @return array
     */
    public function downloadAcademicReportForStudent(User $student, $documentId, $source);
}
