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
use Calendar_Api_Composite;
use Calendar_Api_Config_Record;
use Calendar_Api_EventType_Event_Schoolbox_Campus;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\CampusMapper;
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
     * @var Calendar_Api_Config_Record
     */
    private $calendarConfig;


    /**
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * Constructor
     *
     * @param AssessmentDataSource             $assessmentDataSource
     * @param Visibility                       $visibility
     * @param AssessmentFilters\IdFinder       $assessmentIdFinder
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @param FilterSelection\Mapper           $filterSelectionMapper
     * @param CampusMapper                     $campusMapper
     * @param Calendar_Api_Config_Record       $calendarConfig
     */
    public function __construct(
        AssessmentDataSource $assessmentDataSource,
        Visibility $visibility,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        AssessmentFilters\FactoryFactory $filterFactoryFactory,
        FilterSelection\Mapper $filterSelectionMapper,
        CampusMapper $campusMapper,
        Calendar_Api_Config_Record $calendarConfig
    ) {
        $this->assessmentDataSource  = $assessmentDataSource;
        $this->visibility            = $visibility;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->campusMapper          = $campusMapper;
        $this->calendarConfig        = $calendarConfig;
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
        $user           = $this->getUser();
        $userSelections = $codec->convertRequestToSelections($user, $page, $qsFilters);


        // Store the filters this user selected
        $this->filterSelectionMapper->updateSelections($user, $page, $userSelections);


        // User must have provided a start and end date: error out otherwise
        $start = filter_var($this->request->query->get('start'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $end   = filter_var($this->request->query->get('end'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (!isset($start, $end)) {
            // No you don't throw any exception: only ApplicationException
            /** @noinspection PhpUnhandledExceptionInspection */
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INVALID_DATE);
        }


        // Query the API for the events in this time span
        $api = new Calendar_Api_Composite();


        // Assessments
        $api->add(
            new Api(
                $user,
                $this->assessmentDataSource,
                $this->visibility,
                $this->assessmentIdFinder,
                $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $user, $page)
            )
        );


        // Calendar events: need events from all campuses (not just the
        // logged-in user's), so create a clone who belongs to all campuses
        $dummyUser = clone $user;
        $dummyUser->setCampusCollection($this->campusMapper->findAll());
        $api->add(
            new Calendar_Api_EventType_Event_Schoolbox_Campus(
                $this->calendarConfig->getId(),
                $dummyUser,
                $user
            )
        );
        $calendar = new Renderer(
            $api,
            $start,
            $end,
            $user,
            $this->getDi(),
            $user
        );


        // The API's renderAll returns a list of json-encoded events.
        // Decode them all, and then re-encode.
        $output = array_map('json_decode', $calendar->renderAll(EventBase::RENDER_FORMAT_JSON));
        $this->response->content->set($this->buildJsonOutput($output));
    }
}
