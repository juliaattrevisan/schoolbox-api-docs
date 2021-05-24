<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\ResourceBooking\Resource;

use Payload;
use Payload\PostTrait;

class UpdateBooking extends Payload\Request
{
    use PostTrait;

    var $BookingID;

    public function __construct(\Schoolbox\ResourceBooking\Booking\Model $Booking, string $title)
    {
        $this->BookingID = $Booking->getId();
        $this->setHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->setHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->setHeader('Origin', 'https://vagrant.dev.schoolbox.cloud');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->setReferer('/resource-booking/booking/' . $this->BookingID . "/edit");
        $this->setName($title);
        $this->setEfficacy('self');
        $this->setFormParam('id', $this->BookingID);
        $this->setFormParam('startDate', date('d/m/Y g:ia', strtotime('+ 4 days')));
        $this->setFormParam('endDate', date('d/m/Y g:ia', strtotime('+ 4 days + 1 hour')));
        $this->setFormParam('action', '');
        $this->setFormParam('notes', '<p>These are some notes that have been updated</p>');
        $this->setFormParam('callbackUrl', '');
    }

    public function setName(string $name): void
    {
        $this->setFormParam('name', $name);
    }

    public function setEfficacy(string $efficacy): void
    {
        $this->setFormParam('efficacy', $efficacy);
    }

    public function addRelatedResource($resource_id, $resource_notes)
    {
        $this->setFormParam('related_item[' . $resource_id . '][resource_id]', $resource_id);
        $this->setFormParam('related_item[' . $resource_id . '][notes]', $resource_notes);
    }

    /** @inheritDoc*/
    protected function getUrl(): string
    {
        return "/resource-booking/booking/" . $this->BookingID . "/update";
    }
}
