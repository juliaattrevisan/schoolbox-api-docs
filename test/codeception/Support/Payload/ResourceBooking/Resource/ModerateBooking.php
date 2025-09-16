<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\ResourceBooking\Resource;

use Tests\Support\Payload;
use Tests\Support\Payload\PostTrait;

class ModerateBooking extends Payload\Request
{
    use PostTrait;

    const ACTION_CANCEL = 1;

    const ACTION_REJECT = 2;

    const ACTION_APPROVE = 3;

    private $resourceID;
    private $bookingID;
    private $actionString;

    public function __construct(\Schoolbox\ResourceBooking\Booking\Model $Booking, int $type)
    {
        $this->bookingID  = $Booking->getId();
        $this->resourceID = $Booking->getResource()->getId();
        $this->setHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->setHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('TE', 'Trailers');
        switch ($type) {
            case $this::ACTION_CANCEL:
                $this->actionString = 'cancel';
                $this->setEfficacy('self');
                $this->setQueryParam('callbackUrl', '/resource-booking/item/' . $this->resourceID);
                break;
            case $this::ACTION_REJECT:
                $this->actionString = 'reject';
                $this->setEfficacy('false');
                break;
            case $this::ACTION_APPROVE:
                $this->actionString = 'approve';
                $this->setEfficacy('false');
            default:
                $this->actionString = 'approve';
        }
        $this->setReferer('/resource-booking/item/' . $this->resourceID);
    }

    public function setEfficacy(string $efficacy): void
    {
        $this->setFormParam('efficacy', $efficacy);
    }

    /** @inheritDoc*/
    protected function getUrl(): string
    {
        return "/resource-booking/booking/" . $this->bookingID . "/" . $this->actionString;
    }
}
