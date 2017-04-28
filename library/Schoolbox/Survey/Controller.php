<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Controller extends Base
{
    /** @var UserMapper */
    private $userMapper;

    /** @var SurveyMapper */
    private $surveyMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /**
     * Constructor
     * @param UserMapper $userMapper
     * @param SurveyMapper $surveyMapper
     * @param FolderMapper $folderMapper
     */
    public function __construct(
        UserMapper $userMapper,
        FolderMapper $folderMapper,
        SurveyMapper $surveyMapper
    ) {
        $this->userMapper = $userMapper;
        $this->surveyMapper = $surveyMapper;
        $this->folderMapper = $folderMapper;
    }


    /**
     * Perform an AJAX search for surveys
     *
     * @param string $search
     * @return string
     */
    public function getSelectize()
    {
        return $this->getResponse()->content->set($this->buildJsonOutput(
            $this->surveyMapper->findByKeyword(
                $this->getRequest()->query['query']
            )
        ));
    }
}
