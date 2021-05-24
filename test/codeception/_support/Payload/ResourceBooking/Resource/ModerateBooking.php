<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\ResourceBooking\Resource;

use Payload;
use Payload\PostTrait;

class ModerateBooking extends Payload\Request
{
    use PostTrait;

    const ACTION_CANCEL = 1;

    const ACTION_REJECT = 2;

    const ACTION_APPROVE = 3;

    var $ResourceID;
    var $BookingID;
    var $action_string;

    public function __construct(\Schoolbox\ResourceBooking\Booking\Model $Booking, int $type)
    {
        $this->BookingID = $Booking->getId();
        $this->ResourceID = $Booking->getResource()->getId();
        $this->setHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->setHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->setHeader('Origin', 'https://vagrant.dev.schoolbox.cloud');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('TE', 'Trailers');
        switch ($type) {
            case $this::ACTION_CANCEL:
                $this->action_string = 'cancel';
                $this->setEfficacy('self');
                $this->setQueryParam('callbackUrl', '/resource-booking/item/' . $this->ResourceID);
                break;
            case $this::ACTION_REJECT:
                $this->action_string = 'reject';
                $this->setEfficacy('false');
                break;
            case $this::ACTION_APPROVE:
                $this->action_string = 'approve';
                $this->setEfficacy('false');
            default:
                $this->action_string = 'approve';
        }
        $this->setReferer('/resource-booking/item/' . $this->ResourceID);
    }

    public function setEfficacy(string $efficacy): void
    {
        $this->setFormParam('efficacy', $efficacy);
    }

    /** @inheritDoc*/
    protected function getUrl(): string
    {
        return "/resource-booking/booking/" . $this->BookingID . "/" . $this->action_string;
    }
}
