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
     * Constructor
     *
     * @param Booking\Mapper $bookingMapper
     */
    public function __construct(Booking\Mapper $bookingMapper, Item\Mapper $itemMapper)
    {
        $this
            ->setBookingMapper($bookingMapper)
            ->setItemMapper($itemMapper)
        ;
    }

    /**
     * Shows the resource booking report.
     */
    public function index()
    {
        // Default values: start of today + 1 week
        $defaultTime = Alaress_Date_Format::now()->startOfDay();
        $start       = $this->getRequest()->query->get('start', $defaultTime->getUnixTimeStamp());
        $end         = $this->getRequest()->query->get('end', $defaultTime->addDays(7)->getUnixTimeStamp());

        // Calculate extras
        $categories = $this->getRequest()->query->get('category', []);
        $extras     = [];
        if (!empty($categories)) {
            $extras['resource_id'] = $this->getItemMapper()->findIdsByColumn(
                'resource_category_id',
                $categories
            );
        }

        $this->getResponse()->content->set([
            'template' => '@resourceBookingReport/index.twig',
            'data'     => [
                'start'       => new Alaress_Date_Format($start, Alaress_Date_Format::TIMESTAMP),
                'end'         => new Alaress_Date_Format($end-1, Alaress_Date_Format::TIMESTAMP),
                'bookingList' => $this->getBookingMapper()->findForTimespan(
                    $start,
                    $end,
                    [Booking\Status\Model::STATUS_APPROVED],
                    $extras
                ),
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
}
