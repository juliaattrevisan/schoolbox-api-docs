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
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Item;

class Controller extends Base
{
    /**
     * @var Booking\Mapper
     */
    protected $bookingMapper;

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
     * @param Booking\Mapper  $bookingMapper
     * @param Item\Mapper     $itemMapper
     * @param Category\Mapper $categoryMapper
     */
    public function __construct(
        Booking\Mapper $bookingMapper,
        Item\Mapper $itemMapper,
        Category\Mapper $categoryMapper
    ) {
        $this
            ->setBookingMapper($bookingMapper)
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

        // Fill in extras
        $extras = [];
        if (!empty($selectedCategories)) {
            $extras['resource_id'] = $this->getItemMapper()->findIdsByColumn(
                'resource_category_id',
                $selectedCategories
            );
        } else {
            $extras['resource_id'] = $this->getItemMapper()->findAllIds();
        }

        $this->getResponse()->content->set([
            'template' => '@resourceBookingReport/index.twig',
            'data'     => [
                'categoryList' => $categoryList,
                'bookingList'  => $this->getBookingMapper()->findForTimespan(
                    $start->getUnixTimeStamp(),
                    $end->getUnixTimeStamp(),
                    [Booking\Status\Model::STATUS_APPROVED],
                    $extras
                ),
                'search'       => [
                    'start'    => $start,
                    'end'      => $end,
                    'category' => $selectedCategories,
                ],
            ],
        ]);
    }

    /**
     * @return Booking\Mapper
     */
    protected function getBookingMapper()
    {
        return $this->bookingMapper;
    }

    /**
     * @param  Booking\Mapper $bookingMapper
     * @return $this
     */
    protected function setBookingMapper($bookingMapper)
    {
        $this->bookingMapper = $bookingMapper;
        return $this;
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
