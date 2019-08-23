<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Web\Controller\Base;
use Calendar\Event\Collection\Renderer;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Filter\Page as FilterPage;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Visibility;

/**
 * Controller for retrieving calendar events for the assessment calendar.
 */
class Controller extends Base
{
    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;


    /**
     * @var Visibility
     */
    private $visibility;


    /**
     * @var AssessmentFilters\IdFinder
     */
    private $assessmentIdFinder;


    /**
     * @var AssessmentFilters\FactoryFactory
     */
    private $filterFactoryFactory;


    /**
     * Constructor
     *
     * @param AssessmentDataSource             $assessmentDataSource
     * @param Visibility                       $visibility
     * @param AssessmentFilters\IdFinder       $assessmentIdFinder
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     */
    public function __construct(
        AssessmentDataSource $assessmentDataSource,
        Visibility $visibility,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        AssessmentFilters\FactoryFactory $filterFactoryFactory
    ) {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->visibility           = $visibility;
        $this->assessmentIdFinder   = $assessmentIdFinder;
        $this->filterFactoryFactory = $filterFactoryFactory;
    }


    /**
     * Returns the events for the assessment calendar, as a JSON feed.
     *
     * @api   GET /learning/assessment/calendar/events
     * @since 19.1
     */
    public function getEvents(): void
    {
        $qsFilters = $this->request->query->get('filters', []);
        $page      = FilterPage\Model::PAGE_ASSESSMENT_CALENDAR;

        $codec           = $this->filterFactoryFactory->buildCodec();
        $userSelections  = $codec->convertRequestToSelections($this->getUser(), $page, $qsFilters);

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $this->getUser(), $page);
        // Do something with the user selections. Pass them in to the API?

        // Must have:
        // start and end dates, not greater than 2 months between?
        $calendar = new Renderer(
            new Api(
                $this->getUser(),
                $this->assessmentDataSource,
                $this->visibility,
                $this->assessmentIdFinder,
                $filters
            ),
            $this->request->query->getInt('start'),
            $this->request->query->getInt('end'),
            $this->getUser(),
            $this->getDi(),
            $this->getUser()
        );

        $output = array_map('json_decode', $calendar->renderAll('javascript'));
        $this->response->content->set($this->buildJsonOutput($output));
    }
}
