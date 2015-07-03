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
use Schoolbox\ResourceBooking\Booking;

use Alaress\Paginator\IUrlPaginator;
use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Storage;

class Controller extends Base
{
    /**
     * @var Booking\Mapper
     */
    protected $bookingMapper;

    public function __construct(Booking\Mapper $bookingMapper)
    {
        $this->setBookingMapper($bookingMapper);
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
        $this->getResponse()->content->set([
            'template' => '@resourceBookingReport/index.twig',
            'data'     => [
                'start'       => new Alaress_Date_Format($start, Alaress_Date_Format::TIMESTAMP),
                'end'         => new Alaress_Date_Format($end, Alaress_Date_Format::TIMESTAMP),
                'bookingList' => $this->getBookingMapper()->findForTimespan($start, $end),
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
}
