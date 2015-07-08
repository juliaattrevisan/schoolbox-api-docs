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
use Calendar_Api_Composite;
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
     * @var Calendar_Api_Composite
     */
    protected $api;

    /**
     * @var CalendarApiFactory
     */
    protected $apiFactory;

    /**
     * Constructor
     *
     * @param Item\Mapper            $itemMapper
     * @param Category\Mapper        $categoryMapper
     * @param Calendar_Api_Composite $api
     * @param CalendarApiFactory     $apiFactory
     */
    public function __construct(
        Item\Mapper $itemMapper,
        Category\Mapper $categoryMapper,
        Calendar_Api_Composite $api,
        CalendarApiFactory $apiFactory
    ) {
        $this
            ->setItemMapper($itemMapper)
            ->setCategoryMapper($categoryMapper)
            ->setApi($api)
            ->setApiFactory($apiFactory)
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
        foreach ($resources as $resource) {
            $this->getApi()->add(
                $this->getApiFactory()->build($resource)
            );
        }

        $this->getResponse()->content->set([
            'template' => '@resourceBookingReport/index.twig',
            'data'     => [
                'categoryList' => $categoryList,
                'eventList'    => $this->getApi()->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp()),
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
    protected function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param  Category\Mapper $categoryMapper
     *
     * @return $this
     */
    protected function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }

    /**
     * @return Calendar_Api_Composite
     */
    protected function getApi()
    {
        return $this->api;
    }

    /**
     * @param  Calendar_Api_Composite $api
     * @return $this
     */
    protected function setApi($api)
    {
        $this->api = $api;
        return $this;
    }

    /**
     * @return CalendarApiFactory
     */
    protected function getApiFactory()
    {
        return $this->apiFactory;
    }

    /**
     * @param  CalendarApiFactory $apiFactory
     * @return $this
     */
    protected function setApiFactory($apiFactory)
    {
        $this->apiFactory = $apiFactory;
        return $this;
    }
}
