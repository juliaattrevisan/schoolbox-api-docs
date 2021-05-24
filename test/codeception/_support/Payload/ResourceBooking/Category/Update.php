<?php

namespace Payload\ResourceBooking\Category;

use Payload;
use Payload\PostTrait;

class Update extends Payload\Request
{
    use PostTrait;

    var $catId;

    /**
     */
    public function __construct(\Schoolbox\ResourceBooking\Category\Model $resbook_category)
    {
        $this->catId = $resbook_category->getId();
        $this->setReferer('/resource-booking/category/' . $this->catId . '/edit');
        $this->setID($this->catId);
    }

    public function setCategoryTitle(string $title): Update
    {
        $this->setFormParam('name', $title);
        return $this;
    }

    public function setID(string $id): void
    {
        $this->setFormParam('id', $id);
    }

    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/category/" . $this->catId . '/update';
    }
}
