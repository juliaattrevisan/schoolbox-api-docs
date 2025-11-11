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
use Schoolbox\ResourceBooking\Booking\Status\Model as StatusModel;
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

        $this->api->add(
            $this->apiFactory->buildFromResourceCollection(
                $resources,
                $showClasses
            )
        );

        // we may be editing this list, so temp var it
        $eventList = $this->api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());

        // if we are only showing clashes, filter the event list now
        $clashesOnlyList = new GenericCollection(EventInterface::class);
        if ($clashesOnly) {
            foreach ($eventList as $event) {
                // check if any other events clash with us (overlapping time, same resource)
                $collides = $eventList->filter(function ($otherEvent) use ($event) {
                    /* @var \Calendar\Event\Type\ResourceBooking\Event|\Calendar\Event\Type\Timetable\Event $otherEvent */
                    /* @var \Calendar\Event\Type\ResourceBooking\Event|\Calendar\Event\Type\Timetable\Event $event */

                    // only check for status of pending bookings if they are resource bookings
                    if (
                        $otherEvent instanceof \Calendar\Event\Type\ResourceBooking\Event &&
                        $event instanceof \Calendar\Event\Type\ResourceBooking\Event
                    ) {
                        // skip ourself
                        if ($otherEvent->getBooking()->getId() === $event->getBooking()->getId()) {
                            return false;
                        }

                        // must be same resource
                        if ($otherEvent->getBooking()->getResource()->getId() !== $event->getBooking()->getResource()->getId()) {
                            return false;
                        }

                        // only care if the booking is pending, so we can see conflicts (that we need to action)
                        if ($otherEvent->getBooking()->getStatus()->getId() !== StatusModel::STATUS_PENDING) {
                            return false;
                        }
                    } elseif (
                        $event instanceof \Calendar\Event\Type\Timetable\Event &&
                        $otherEvent instanceof \Calendar\Event\Type\Timetable\Event
                    ) { // if not booking, then assume its a timetable event, which may have a resource id, so check that
                        if ((int)$otherEvent->getResourceId() !== (int)$event->getResourceId()) {
                            return false;
                        }

                        // check if the for the same homepage (class)
                        if ($event->getCategoryId() === $otherEvent->getCategoryId()) {
                            return false;
                        }
                    }

                    // check for time overlap (for collisions)
                    if (
                        $event->getStartTime()->getDateTimeObject()->getTimestamp() <= $otherEvent->getEndTime()->getDateTimeObject()->getTimestamp()
                        && $event->getEndTime()->getDateTimeObject()->getTimestamp() >= $otherEvent->getStartTime()->getDateTimeObject()->getTimestamp()
                    ) {
                        return true;
                    }

                    // no collision, safe to ignore
                    return false;
                });
                // if we have any collisions, add them to the list
                foreach ($collides as $collideEvent) {
                    $clashesOnlyList->add($collideEvent);
                }
            }
            // collisions should overwrite the event list
            $eventList = $clashesOnlyList;
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
