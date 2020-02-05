<?php
namespace Payload\ResourceBooking\Category;


//to https://vagrant.dev.schoolbox.cloud/
//from https://vagrant.dev.schoolbox.cloud/resource-booking/category/add
/*
 * id	
name	Stuff
 */

use Payload;

use Payload\PostTrait;

class Create extends Payload\Request
{
    use PostTrait;
    
    /**
     */
    public function __construct()
    {
        $this->setReferer('/resource-booking/category/add');
        $this->setID('');
    }
    
    public function setCategoryTitle(string $title): Create
    {
        $this->setFormParam('name', $title);
        return $this;
    }
    
    public function setID(string $id):void
    {
        $this->setFormParam('id', $id);
    }
    
    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/category";
    }
}
    