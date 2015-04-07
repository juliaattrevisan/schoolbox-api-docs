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
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;

class Base extends RendererBase
{
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

    /**
     * Helper function - gets whether access to a route is allowed.
     *
     * @param string $name
     * @param array  $params
     *
     * @return bool|string False if not authorized, a URL if it is.
     */
    public function getRouteIfAuthorized($name, array $params = array())
    {
        if ($this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $this->getViewer())) {
            return $this->getRouter()->generate($name, $params);
        }
        return false;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer() {
        return $this->getPage()->getDi()->get('authorizer');
    }

    /**
     * @return Router
     */
    public function getRouter() {
        return $this->getPage()->getDi()->get('router');
    }
} 