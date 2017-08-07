<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\User\Profile\DocumentManagement;

use Alaress\Web\Controller\Base;
use External_Document;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /** @var UserMapper $userMapper */
    private $userMapper;

    /** @var External_Document $externalDocument */
    private $externalDocument;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper) {
        $this->userMapper = $userMapper;
        $this->externalDocument = new External_Document();
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
}
