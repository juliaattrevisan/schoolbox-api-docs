<?php
/**
 * Controller for resource booking report.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Report;

use Alaress_Date_Format;
use Alaress\Web\Controller\Base;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Item;

class Controller extends Base
{
    /**
     * @var Item\Mapper
     */
    protected $itemMapper;

    /**
     * @var Category\Mapper
     */
    protected $categoryMapper;

    /**
     * Constructor
     *
     * @param Item\Mapper     $itemMapper
     * @param Category\Mapper $categoryMapper
     */
    public function __construct(
        Item\Mapper $itemMapper,
        Category\Mapper $categoryMapper
    ) {
        $this
            ->setItemMapper($itemMapper)
            ->setCategoryMapper($categoryMapper)
        ;
    }

    /**
     * Shows the resource booking report.
     */
    public function index()
    {
        // Default values: start of today + 1 week
        /** @var Alaress_Date_Format $start */
        $start = Alaress_Date_Format::now()->startOfDay();
        if ($formStart = $this->getRequest()->query->get('start')) {
            try {
                $start = Alaress_Date_Format::newLocalShort($formStart);
            } catch (\Exception $e) {
                // noop - use default
            }
        }

        /** @var Alaress_Date_Format $end */
        $end = clone $start;
        $end->addDays(7);
        if ($formEnd = $this->getRequest()->query->get('end')) {
            try {
                $end = Alaress_Date_Format::newLocalShort($formEnd);
            } catch (\Exception $e) {
                // noop - use default
            }
        }

        // Set up category list
        $categoryList = [];
        foreach ($this->getCategoryMapper()->findAll(['order' => ['name' => 'asc']]) as $category) {
            $categoryList[$category->getId()] = $category;
        }
        $allCategories = array_keys($categoryList);

        // Filter out non-existent categories from $selectedCategories
        $selectedCategories = $this->getRequest()->query->get('category', []);
        if (!empty($selectedCategories)) {
            $selectedCategories = array_intersect($selectedCategories, $allCategories);
        } else {
            $selectedCategories = $allCategories;
        }

        // Fill in resources
        /** @var Item\Model[] $resources */
        if (!empty($selectedCategories)) {
            $resources = $this->getItemMapper()->findByColumn(
                'resource_category_id',
                $selectedCategories
            );
        } else {
            $resources = $this->getItemMapper()->findAll();
        }

        // Build the list of events (slowly)
        $api       = new \Calendar_Api_Composite();
        $timetable = new \External_Timetable();
        foreach ($resources as $resource) {
            $resourceApi = new \Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
                $this->getUser(),
                array()
            );
            $resourceApi->setResourceId($resource->getId());
            $api->add($resourceApi);

            if ($roomCode = $resource->getRoomCode()) {
                $timetableApi = new \Calendar_Api_EventType_Timetable_Room(
                    $this->getUser(),
                    array()
                );
                $timetableApi->setRoomCode($roomCode)->setExternalTimetable($timetable);
                $api->add($timetableApi);
            }
        }

        $this->getResponse()->content->set([
            'template' => '@resourceBookingReport/index.twig',
            'data'     => [
                'categoryList' => $categoryList,
                'eventList'    => $api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp()),
                'search'       => [
                    'start'    => $start,
                    'end'      => $end,
                    'category' => $selectedCategories,
                ],
            ],
        ]);
    }

    /**
     * @return Item\Mapper
     */
    protected function getItemMapper()
    {
        return $this->itemMapper;
    }

    /**
     * @param  Item\Mapper $itemMapper
     *
     * @return $this
     */
    protected function setItemMapper($itemMapper)
    {
        $this->itemMapper = $itemMapper;
        return $this;
    }

    /**
     * @return Category\Mapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param  Category\Mapper $categoryMapper
     *
     * @return $this
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }
}
