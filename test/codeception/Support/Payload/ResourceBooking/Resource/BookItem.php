<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\ResourceBooking\Resource;

use Tests\Support\Payload;
use Tests\Support\Payload\PostTrait;

class BookItem extends Payload\Request
{
    use PostTrait;

    private $ItemID;

    /**
     * Hit /resource-booking/category url with POST to create a category.
     *
     * @param \Schoolbox\ResourceBooking\Item\Model $Item
     * @param string $title
     */
    public function __construct(\Schoolbox\ResourceBooking\Item\Model $Item, string $title)
    {
        $this->ItemID = $Item->getId();
        $this->setHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->setHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->setHeader('Origin', 'https://vagrant.dev.schoolbox.cloud');
        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->setReferer('/resource-booking/booking/bookItem/' . $this->ItemID);
        $this->setName($title);
        $this->setEfficacy('self');
        $this->setFormParam('id', '');
        $this->setFormParam('startDate', date('d/m/Y g:ia', strtotime('+ 4 days')));
        $this->setFormParam('endDate', date('d/m/Y g:ia', strtotime('+ 4 days + 1 hour')));
        $this->setFormParam('action', '');
        $this->setFormParam('campus', '1');
        $this->setFormParam('repeat', '');
        $this->setFormParam('repeat_untill', '');
        $this->setFormParam('notes', '<p>These are some notes</p>');
        $this->setFormParam('callbackUrl', '');
    }
    public function constructWithDate(\Schoolbox\ResourceBooking\Item\Model $Item, string $title, string $startDate, string $endDate): BookItem
    {
        $obj = new Payload\ResourceBooking\Resource\BookItem($Item, $title);
        $obj->setStartDate($startDate);
        $obj->setEndDate($endDate);
        return $obj;

    }

    public function setName(string $name)
    {
        $this->setFormParam('name', $name);
    }

    public function setEfficacy(string $efficacy)
    {
        $this->setFormParam('efficacy', $efficacy);
    }

    public function setStartDate(string $startDate)
    {
            $this->setFormParam('startDate', $startDate);
    }

    public function setEndDate(string $endDate): void
    {
            $this->setFormParam('endDate', $endDate);
    }

    public function addRelatedResource($resource_id, $resource_notes)
    {
        $this->setFormParam('related_item[' . $resource_id . '][resource_id]', $resource_id);
        $this->setFormParam('related_item[' . $resource_id . '][notes]', $resource_notes);
    }

    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/booking/bookItem/" . $this->ItemID;
    }
}
