<?php

/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Offline\Base;

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
    public function getAcademicReportsForStudent(User $student, array $classificationCodes): array
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @param User   $student
     * @param string $documentId
     * @param string $source
     * @param array  $classificationCodes
     *
     * @return void
     * @throws \Exception
     */
    public function downloadAcademicReportForStudent(User $student, $documentId, $source, array $classificationCodes)
    {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
    }
}
