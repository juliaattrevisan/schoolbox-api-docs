<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Web\Controller\Base;
use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Filter\Page as FilterPage;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\Exception as MapperException;

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
     * @var FilterSelection\Mapper
     */
    private $filterSelectionMapper;


    /**
     * Constructor
     *
     * @param AssessmentDataSource             $assessmentDataSource
     * @param Visibility                       $visibility
     * @param AssessmentFilters\IdFinder       $assessmentIdFinder
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @param FilterSelection\Mapper           $filterSelectionMapper
     */
    public function __construct(
        AssessmentDataSource $assessmentDataSource,
        Visibility $visibility,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        AssessmentFilters\FactoryFactory $filterFactoryFactory,
        FilterSelection\Mapper $filterSelectionMapper
    ) {
        $this->assessmentDataSource  = $assessmentDataSource;
        $this->visibility            = $visibility;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterSelectionMapper = $filterSelectionMapper;
    }


    /**
     * Returns the events for the assessment calendar, as a JSON feed.
     *
     * @api   GET /learning/assessment/calendar/events
     * @since 19.1
     *
     * @throws MapperException      if somehow a mapper call couldn't create a model?
     * @throws ApplicationException if start and end weren't both supplied as query string params
     */
    public function getEvents(): void
    {
        $qsFilters      = $this->request->query->get('filters', []);
        $page           = FilterPage\Model::PAGE_ASSESSMENT_CALENDAR;
        $codec          = $this->filterFactoryFactory->buildCodec();
        $userSelections = $codec->convertRequestToSelections($this->getUser(), $page, $qsFilters);


        // Store the filters this user selected
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $userSelections);


        // User must have provided a start and end date: error out otherwise
        $start = filter_var($this->request->query->get('start'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $end   = filter_var($this->request->query->get('end'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (!isset($start, $end)) {
            // No you don't throw any exception: only ApplicationException
            /** @noinspection PhpUnhandledExceptionInspection */
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INVALID_DATE);
        }


        // Query the API for the events in this time span
        $api = new \Calendar_Api_Composite();
        $api->add(
            new Api(
                $this->getUser(),
                $this->assessmentDataSource,
                $this->visibility,
                $this->assessmentIdFinder,
                $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $this->getUser(), $page)
            )
        );
        $api->add(
            new \Calendar_Api_EventType_Event_Schoolbox_Campus(
                \Calendar_Api_Config_Mapper::getInstance()->findOneForClass(\Calendar_Api_EventType_Event_Schoolbox_Campus::class)->getId(),
                $this->getUser(),
                $this->getUser()
            )
        );
        $calendar = new Renderer(
            $api,
            $start,
            $end,
            $this->getUser(),
            $this->getDi(),
            $this->getUser()
        );


        // The API's renderAll returns a list of json-encoded events.
        // Decode them all, and then re-encode.
        $output = array_map('json_decode', $calendar->renderAll(EventBase::RENDER_FORMAT_JSON));
        $this->response->content->set($this->buildJsonOutput($output));
    }
}
