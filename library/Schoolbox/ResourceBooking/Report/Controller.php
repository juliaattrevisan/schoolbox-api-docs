<?php

/**
 * Controller for resource booking report.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Report;

use Alaress_Date_Format;
use Alaress\Web\Controller\Base;
use Calendar\Event\Type\EventInterface;
use Calendar_Api_Composite;
use Schoolbox\GenericCollection;
use Calendar\Event\Type\Day\Event as Event;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Item\Mapper;

class Controller extends Base
{
    /**
     * The default maximum reporting period for resource bookings
     */
    protected const MAX_REPORTING_PERIOD_MONTHS = 3;

    /**
     * Constructor
     *
     * @param Mapper $itemMapper
     * @param Category\Mapper $categoryMapper
     * @param Calendar_Api_Composite $api
     * @param CalendarApiFactory $apiFactory
     */
    public function __construct(
        protected Item\Mapper $itemMapper,
        protected Category\Mapper $categoryMapper,
        protected Calendar_Api_Composite $api,
        protected CalendarApiFactory $apiFactory,
    ) {
    }

    /**
     * Shows the resource booking report.
     */
    public function index()
    {
        // Default values: start of today + 1 week
        /** @var Alaress_Date_Format $start */
        $start = Alaress_Date_Format::now()->startOfDay();
        if ($formStart = $this->requestQuery->get('start')) {
            try {
                $start = Alaress_Date_Format::newLocalShort($formStart);
            } catch (\Exception $e) {
                // noop - use default
            }
        }

        /** @var Alaress_Date_Format $end */
        $end = clone $start;
        $end->addDays(7);
        if ($formEnd = $this->requestQuery->get('end')) {
            try {
                $end = Alaress_Date_Format::newLocalShort($formEnd);

                // The MAX_REPORTING_PERIOD_MONTHS restriction is enforced at the client too.
                // So check ResourceBooking/Report/templates/index.twig after any changes here.
                if ($start->diffInMonths($end) > self::MAX_REPORTING_PERIOD_MONTHS) {
                    $end = clone $start;
                    $end->addMonths(self::MAX_REPORTING_PERIOD_MONTHS);
                }
            } catch (\Exception $e) {
                // noop - use default
            }
        }

        // Add time component to $start and $end dates to ensure date range is inclusive at both ends.
        $end->addHours(23)->addMinutes(59)->addSeconds(59);

        // Set up category list
        $categoryList = [];
        foreach ($this->categoryMapper->findAll(['order' => ['name' => 'asc']]) as $category) {
            $categoryList[$category->getId()] = $category;
        }
        $allCategories = array_keys($categoryList);

        // Filter out non-existent categories from $selectedCategories
        $selectedCategories = (array)($this->requestQuery->get('category', []));
        if (!empty($selectedCategories)) {
            $selectedCategories = array_intersect($selectedCategories, $allCategories);
        } else {
            $selectedCategories = $allCategories;
        }

        // Fill in resources
        if (!empty($selectedCategories)) {

            /** @var Item\Model[] $resources */
            $resources = $this->itemMapper->findByColumn(
                'resource_category_id',
                $selectedCategories
            );
        } else {

            /** @var Item\Model[] $resources */
            $resources = $this->itemMapper->findAll();
        }

        // Build the list of events (slowly)
        $showClasses = $this->requestQuery->getBool('classes', true);
        $clashesOnly = $this->requestQuery->getBool('clashesOnly', false);
        $capacityAllocation = new \Schoolbox\ResourceBooking\Booking\CapacityAllocation();

        $this->api->add(
            $this->apiFactory->buildFromResourceCollection(
                $resources,
                $showClasses
            )
        );

        /*
         * $eventList may be edited (if only looking for clashes), so cache that,
         * and we only have specific types provided from buildFromResourceCollection explicitly set the types of events
         * \Calendar\Event\Type\ResourceBooking\Event
         * \Calendar\Event\Type\Timetable\Event
         * \Calendar\Event\Type\SudsTimetable\Event
         */
        /** @var GenericCollection<\Calendar\Event\Type\ResourceBooking\Event | \Calendar\Event\Type\Timetable\Event | \Calendar\Event\Type\SudsTimetable\Event> $eventList */
        $eventList = $this->api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());

        // if we are only showing clashes, filter the event list now
        /** @var array<string, Event> $clashesOnlyList */
        $clashesOnlyList = [];
        if ($clashesOnly) {
            // Step 1: Group events by resource ID
            /** @var array<int|string, Event[]> $eventsByResource */
            $eventsByResource = [];
            foreach ($eventList as $event) {
                 // Due to hydration differences between resource bookings and timetable events, we need to extract the resource ID
                $resourceId = ($event instanceof \Calendar\Event\Type\ResourceBooking\Event)
                    ? $event->getBooking()->getResource()->getId()
                    : $event->getResourceId();
                if (!isset($eventsByResource[$resourceId])) {
                    $eventsByResource[$resourceId] = [];
                }
                $eventsByResource[$resourceId][] = $event;
            }
            // Step 2: For each resource, sort events by start time and check for overlaps
            foreach ($eventsByResource as $resourceEvents) {
                $resourceBookingEvents = array_values(array_filter(
                    $resourceEvents,
                    static fn ($event): bool => $event instanceof \Calendar\Event\Type\ResourceBooking\Event
                ));

                foreach ($resourceBookingEvents as $resourceBookingEvent) {
                    $blockingEvents = $capacityAllocation->getBlockingApprovedBookingEvents(
                        $resourceBookingEvent,
                        $resourceBookingEvents
                    );
                    if (count($blockingEvents) === 0) {
                        continue;
                    }

                    $clashesOnlyList[spl_object_hash($resourceBookingEvent)] = $resourceBookingEvent;
                    foreach ($blockingEvents as $blockingEvent) {
                        $clashesOnlyList[spl_object_hash($blockingEvent)] = $blockingEvent;
                    }
                }

                // Sort by start time
                usort($resourceEvents, function ($a, $b) {
                    return $a->getStartTime()->compare($b->getStartTime());
                });
                $count = count($resourceEvents);
                for ($i = 0; $i < $count; $i++) {
                    $eventA = $resourceEvents[$i];

                    $eventAHash = spl_object_hash($eventA);
                    for ($j = $i + 1; $j < $count; $j++) {
                        $eventB = $resourceEvents[$j];
                        // break early if there's no chance of a clash
                        // we have sorted above by start time, so if eventB starts after eventA ends, no need to check further
                        if ($eventB->getStartTime()->gt($eventA->getEndTime())) {
                            break;
                        }

                        if (
                            !(
                                $eventA instanceof \Calendar\Event\Type\ResourceBooking\Event
                                && $eventB instanceof \Calendar\Event\Type\ResourceBooking\Event
                            )
                            && $eventA->isClash($eventB)
                        ) {
                            $eventBHash = spl_object_hash($eventB);
                            // Add both events to the clash list
                            $clashesOnlyList[$eventAHash] = $eventA;
                            $clashesOnlyList[$eventBHash] = $eventB;
                        }
                    }
                }
            }
            // event list will now only contain clashes (seeing as we requested them)
            $eventList = new GenericCollection(EventInterface::class, $clashesOnlyList);
        }

        $this->response = $this->responseFromTemplate(
            '@resourceBookingReport/index.twig',
            [
                'categoryList' => $categoryList,
                'eventList'    => $eventList,
                'search'       => [
                    'start'    => $start,
                    'end'      => $end,
                    'category' => $selectedCategories,
                    'classes'  => $showClasses,
                    'clashesOnly'  => $clashesOnly,
                ],
            ],
        );
    }
}
