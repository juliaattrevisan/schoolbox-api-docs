<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Upcoming;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\ImplementationMapperProxy as CourseImplementationDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\User;

/**
 * Controller for the upcoming work screen.
 */
class Controller extends Base
{
    /**
     * Redirects to the upcoming work screen for the current user.
     *
     * @api GET /learning/due/
     */
    public function getUpcoming()
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningUpcoming.getUpcomingForStudent',
                ['studentId' => $this->getUser()->getId(),]
            )
        );
    }

    /**
     * Displays upcoming work for the given user.
     *
     * @api   GET /learning/due/:studentId
     * @param User $student
     */
    public function getUpcomingForStudent(User $student)
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningUpcoming/upcoming.twig',
                ['student' => $student,]
            )->setTitle('Upcoming Work for '.$student->formatName())
        );
    }
}
