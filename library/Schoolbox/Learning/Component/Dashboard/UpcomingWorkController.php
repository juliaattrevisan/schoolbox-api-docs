<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Visibility;
use \Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;

/**
 * UPCOMING DUE WORK COMPONENT
 * Displays a list of upcoming Quiz + DueWork (but not task) for students only
 */


class UpcomingWorkController extends Component_Dashboard_ControllerAbstract
{
    // Should match the DB value
    private const LIMIT_DEFAULT = 10;

    /**
     * The upcoming Quiz + DueWork that are visible to this Student
     *
     * @var IAssessable[]|Collection
     */
    private $upcomingAssessments;


    /**
     * The maximum number of items to show
     *
     * @var int
     */
    private $limit;

    /**
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentDataSource;


    /**
     * Business logic to collate data ready for output
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function execute(): bool
    {
        $this->limit = is_numeric($this->getConfig('size')) ? $this->getConfig('size') : self::LIMIT_DEFAULT;

        $this->assessmentDataSource = $this->getSitePage()->getDi()->get('assessmentDataSource');
        $this->termMapper = $this->getMapperFactory()->get(TermMapper::class);

        $term = $this->termMapper->findForToday();
        $student = $this->getUser();

        $visibility                = new Visibility($this->getAccess());
        $this->upcomingAssessments = $this->assessmentDataSource
            ->findAllByStudent($student, true, $term)
            ->sortByCallback(new DueDateSortStrategy($student))
            ->filter(function (IAssessable $assessable) use ($student, $visibility) {
                // Only show work that the student has permission to view
                return $visibility->isVisibleToUser($assessable, $student, false);
            })
            ->limit($this->limit ?: null);

        return \count($this->upcomingAssessments) > 0;
    }

    /**
     * Output the data we collected in exec()
     *
     * @param  String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/upcomingAssessments.twig',
            [
                'assessments' => $this->upcomingAssessments,
                'limit'       => $this->limit,
                'returnUrl'   => Redirect::queryString('/')
            ]
        );
    }
}
