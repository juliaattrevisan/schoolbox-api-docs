<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\User;

class External_Schoolbox_Document implements External_DocumentInterface
{
    /**
     * @inheritdoc
     *
     * @param User $student
     * @return array
     */
    public function getAcademicReportsForStudent(User $student)
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @param User $student
     * @param string $documentId
     * @return array
     * @throws SchoolboxException
     */
    public function downloadAcademicReport(User $student, $documentId)
    {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
    }
}
