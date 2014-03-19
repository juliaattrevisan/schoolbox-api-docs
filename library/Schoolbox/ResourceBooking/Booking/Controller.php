<?php
/**
 * Controller for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;
use Alaress\Web\Controller\Base;
use Schoolbox\ResourceBooking\Item;

class Controller extends Base {

    /**
     * @var Item\Mapper
     */
    protected $resourceMapper;

    /**
     * Creates a booking for an item.
     */
    public function book($id) {
        /** @var Item\Model $resource */
        $resource = $this->getResourceMapper()->findById($id);
        $response = $this->getResponse();
        $response->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'resource' => $resource,
                ),
            )
        );
    }

    /**
     * @param  Item\Mapper $resourceMapper
     * @return $this
     */
    public function setResourceMapper($resourceMapper) {
        $this->resourceMapper = $resourceMapper;
        return $this;
    }

    /**
     * @return Item\Mapper
     */
    public function getResourceMapper() {
        return $this->resourceMapper;
    }

}
