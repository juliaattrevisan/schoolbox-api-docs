<?php

/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\ResourceBooking\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\ResourceBooking;
use Schoolbox\User;

abstract class Base extends RendererBase
{
    /** @var ResourceBooking\Event */
    protected $event;

    protected ?string $exportType = null;

    /**
     * Constructor
     *
     * @param Authorizer            $authorizer
     * @param Generator             $routeGenerator
     * @param ResourceBooking\Event $event
     * @param User                  $user
     * @param User                  $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, ResourceBooking\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);
        $this->event = $event;
    }

    public function getEditURL()
    {
        return $this->getRouteIfAuthorized(
            'resourceBooking.edit',
            array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
        );
    }

    public function getCancelURL()
    {
        return $this->getRouteIfAuthorized(
            'resourceBooking.cancel',
            array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
        );
    }

    public function getApproveURL()
    {
        if (!$this->getEvent()->getBooking()->getStatus()->isApproved()) {
            return $this->getRouteIfAuthorized(
                'resourceBooking.approve',
                array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
            );
        } else {
            return '';
        }
    }

    public function getRejectURL()
    {
        if (!$this->getEvent()->getBooking()->getStatus()->isRejected() && !$this->getEvent()->getBooking()->getStatus()->isApproved()) {
            return $this->getRouteIfAuthorized(
                'resourceBooking.reject',
                array('id' => $this->getEvent()->getBooking()->getId(), 'item' => $this->getEvent()->getBooking(),)
            );
        } else {
            return '';
        }
    }

    /**
     * @return ResourceBooking\Event
     */
    protected function getEvent(): ResourceBooking\Event
    {
        return $this->event;
    }

    /**
     * @return ?string
     */
    public function getExportType(): ?string
    {
        return $this->exportType;
    }

    /**
     * @param string $exportType
     * @return $this
     */
    public function setExportType(string $exportType): self
    {
        $this->exportType = $exportType;
        return $this;
    }
}
