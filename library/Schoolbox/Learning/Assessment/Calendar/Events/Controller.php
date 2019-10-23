<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;
use Calendar_Api_Composite;
use Calendar_Api_Config_Record;
use Calendar_Api_EventType_Event_Schoolbox_Campus;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Filter\Filters\Event as EventFilters;
use Schoolbox\Filter\IFactoryFactory;
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
     * @var IFactoryFactory
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
     * @param AssessmentDataSource       $assessmentDataSource
     * @param Visibility                 $visibility
     * @param AssessmentFilters\IdFinder $assessmentIdFinder
     * @param IFactoryFactory            $filterFactoryFactory
     * @param FilterSelection\Mapper     $filterSelectionMapper
     * @param CampusMapper               $campusMapper
     * @param Calendar_Api_Config_Record $calendarConfig
     */
    public function __construct(
        AssessmentDataSource $assessmentDataSource,
        Visibility $visibility,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        IFactoryFactory $filterFactoryFactory,
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
        $this->response->content->set($this->buildJsonOutput($this->buildEventOutput()));
    }


    /**
     * Returns the grouped events for the assessment calendar, as a JSON feed.
     *
     * @api   GET /learning/assessment/calendar/events/grouped
     * @since 19.1
     *
     * @throws MapperException      if somehow a mapper call couldn't create a model?
     * @throws ApplicationException if start and end weren't both supplied as query string params
     */
    public function getGroupedEvents(): void
    {
        $this->response->content->set(
            $this->buildJsonOutput(
                array_values(array_reduce($this->buildEventOutput(), [$this, 'groupEvents'], []))
            )
        );
    }


    /**
     * Build a list of events to output.
     *
     * @return array
     * @throws MapperException      if somehow a mapper call couldn't create a model?
     * @throws ApplicationException if start and end weren't both supplied as query string params
     */
    private function buildEventOutput(): array
    {
        $qsFilters      = $this->request->query->get('filters', []);
        $page           = FilterPage\Model::PAGE_ASSESSMENT_CALENDAR;
        $codec          = $this->filterFactoryFactory->buildCodec();
        $user           = $this->getUser();
        $userSelections = $codec->convertRequestToSelections($user, $page, $qsFilters);
        $filters        = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $user, $page);


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
                $filters
            )
        );


        // Calendar events: need events from all campuses
        // (not just the logged-in user's), so create a clone in all campuses
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
        $calendar->getAllEvents()->filter(new EventFilters\Matcher($filters));


        // The API's renderAll returns a list of json-encoded events.
        // Decode them all, and then re-encode.
        return array_map(
            static function ($v) {
                return json_decode($v, true);
            },
            $calendar->renderAll(EventBase::RENDER_FORMAT_JSON)
        );
    }


    /**
     * Reduce function for grouping all events by their resource.
     *
     * @param array|null $carry
     * @param array      $event
     * @return array
     */
    private function groupEvents(?array $carry, array $event): array
    {
        if (!isset($carry)) {
            $carry = [];
        }

        // What's the event's resource?
        $resourceId = null;
        if (isset($event['data']['meta']['folderId'])) {
            $resourceId = sprintf('folder-%d', $event['data']['meta']['folderId']);
        } elseif (isset($event['data']['meta']['eventTypeId'])) {
            $resourceId = sprintf('eventType-%d', $event['data']['meta']['eventTypeId']);
        }


        // Year/week grouping:
        //         self.toMoment(element.start).format('YYYY')
        //         + String(self.toMoment(element.start).isoWeek()).padStart(2, 0)
        $grouping = null;
        if ($resourceId) {
            $start    = new Alaress_Date_Format(
                $event['start'],
                [Alaress_Date_Format::DATABASE_FORMAT, Alaress_Date_Format::DATABASE_DATE_FORMAT]
            );
            $grouping = sprintf('%s-%d-%02d', $resourceId, $start->getYear(), $start->getUsingFormat('W'));

            if (!isset($carry[$grouping])) {
                $carry[$grouping]          = [
                    'allday'     => false,
                    'editable'   => false,
                    'draggable'  => false,
                    'resourceId' => $resourceId,
                    'title'      => 0,
                    'start'      => $start->getDatabaseDate(),
                    'end'        => null,
                    'data'       => [
                        'placeholder' => true,
                        'meta'        => [
                            'folderId'      => $event['data']['meta']['folderId']      ?? null,
                            'folderName'    => $event['data']['meta']['folderName']    ?? null,
                            'eventTypeId'   => $event['data']['meta']['eventTypeId']   ?? null,
                            'eventTypeName' => $event['data']['meta']['eventTypeName'] ?? null,
                        ],
                        'subEvents' => [],
                    ],
                ];
                $carry[$grouping]['title'] = 0;
            }

            $carry[$grouping]['title']++;

            // Populate some fields of sub-event
            $carry[$grouping]['data']['subEvents'][] = [
                'title' => $event['title'],
                'start' => $event['start'],
                'end'   => $event['end'],
            ];
        }

        return $carry;
    }
}
