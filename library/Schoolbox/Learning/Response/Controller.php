<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Response;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Timeline;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

use \Schoolbox\Learning\Assessment\DueWork\TemporaryDataDecorator;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->setErrorMapper($errorMapper);
    }

    /**
     * Get a list of Responses by $student for a particular
     * IAssessable $assessment
     *
     * @param IAssessable $assessment
     * @param User $student
     */
    public function getResponseList(IAssessable $assessment, User $student)
    {
        /* TODO: Don't use temporary data
         * Ram in some temporary responses so that I
         * can make the Submission History section
         */
        (new TemporaryDataDecorator())->decorate($assessment);

        $data = [
            'assessment' => $assessment,
            'student' => $student,
            'timeline' => (new Timeline($assessment))->getTimeline()
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningResponse/responseList.twig', $data));
    }

    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return $this
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}
