<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\ResourceBooking\Category;

use Payload;
use Payload\PostTrait;

class Create extends Payload\Request
{
    use PostTrait;

    public function __construct()
    {
        $this->setReferer('/resource-booking/category/add');
        $this->setID('');
    }

    public function setCategoryTitle(string $title): self
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
        return "/resource-booking/category";
    }
}
