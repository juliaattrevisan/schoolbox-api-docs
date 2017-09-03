<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Offline\Base;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IDocument;
use Schoolbox\User;

class Document extends Base implements IDocument
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
    public function downloadAcademicReportForStudent(User $student, $documentId)
    {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
    }
}
