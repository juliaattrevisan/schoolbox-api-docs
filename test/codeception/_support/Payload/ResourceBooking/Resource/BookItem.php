<?php
namespace Payload\ResourceBooking\Resource;

use Payload;

use Payload\PostTrait;

//https://vagrant.dev.schoolbox.cloud/resource-booking/booking/bookItem/16
// Referer: https://vagrant.dev.schoolbox.cloud/resource-booking/booking/bookItem/16?startDate=2020-04-19&startTime=14:00&callbackUrl=%2Fresource-booking%2Fcategory%2F1
/*
 * callbackUrl	/resource-booking/category/1
 efficacy	self
 action
 name	srtstys4tys4ts4tsetsetetsertfgdxgf
 startDate	24/04/2020 2:00pm
 endDate	25/04/2020 2:00pm
 repeat
 campus	1
 repeat_until
 notes	<p>afasfewfrawr3ra3rawer</p>
 related_item[14][notes]
 related_item[28][resource_id]	28
 related_item[28][notes]	<p>afargrasg+afgsdg</p> 
 */

class BookItem extends Payload\Request
{
    use PostTrait;
    
    var $ItemID;
    
    public function __construct(\Schoolbox\ResourceBooking\Item\Model $Item, string $title)
    {
        $this->ItemID=$Item->getId();
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
    
    public function setName(string $name)
    {
        $this->setFormParam('name', $name);
    }
    
    public function setEfficacy(string $efficacy)
    {
        $this->setFormParam('efficacy', $efficacy);
    }
    
    public function addRelatedResource($resource_id, $resource_notes)
    {
        $this->setFormParam('related_item['. $resource_id .'][resource_id]', $resource_id);
        $this->setFormParam('related_item['. $resource_id .'][notes]', $resource_notes);
    }
    
    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/resource-booking/booking/bookItem/" . $this->ItemID;
    }
    
}