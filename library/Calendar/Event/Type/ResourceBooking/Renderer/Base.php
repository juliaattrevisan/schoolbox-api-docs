<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\ResourceBooking\Renderer;

use \Calendar\Event\Type\RendererBase;

/**
 * Class Base
 *
 * @method \Calendar\Event\Type\ResourceBooking\Event getEvent()
 */
abstract class Base extends RendererBase
{

    /**
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    public function getEditURL() {
        return $this->getRouteIfAuthorized(
            'resourceBooking.edit',
            array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
        );
    }

    public function getCancelURL() {
        return $this->getRouteIfAuthorized(
            'resourceBooking.cancel',
            array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
        );
    }

    public function getApproveURL() {
        if (!$this->getEvent()->getBooking()->getStatus()->isApproved()) {
            return $this->getRouteIfAuthorized(
                'resourceBooking.approve',
                array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
            );
        }
        else {
            return '';
        }
    }

    public function getRejectURL() {
        if (!$this->getEvent()->getBooking()->getStatus()->isRejected() && !$this->getEvent()->getBooking()->getStatus()->isApproved()) {
            return $this->getRouteIfAuthorized(
                'resourceBooking.reject',
                array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
            );
        }
        else {
            return '';
        }
    }
}
