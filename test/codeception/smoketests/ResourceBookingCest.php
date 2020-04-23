<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace SmokeTests;

use IntegrationTester;
use Payload;

/**
 * Tests:
 * * 
 * * 
 *
 * @noinspection PhpUnused
 */
class ResrouceBookingCest
{
    /**
     * Hook method to execute prior to each test
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }
    
    public function createCategory(IntegrationTester $i, Payload\ResourceBooking\Category\Create $category): void
    {
        #GIVEN
        $timestamp  = time();
        $category_title = 'Testing Caregory Create at ' . $timestamp;
        
        #WHEN
        $category->setCategoryTitle($category_title);
        $i->sendRequest($category);
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/category");
    }
    
    public function updateCategory(IntegrationTester $i, Payload\ResourceBooking\Category\Create $category): void
    {
        #GIVEN
        $timestamp  = time();
        $category_title = 'Testing Caregory Update at ' . $timestamp;
        
        #WHEN
        $category->setCategoryTitle($category_title);
        $i->sendRequest($category);
        
        $CatMapper = \Schoolbox\ResourceBooking\Category\Mapper::getInstance();
        $newCat = $CatMapper->findOneByColumn('name', $category_title);
        
        $updatePayload= new Payload\ResourceBooking\Category\Update($newCat);
        $updatePayload->setCategoryTitle($category_title . ' Updated');
        $i->sendRequest($updatePayload);
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/category");
    }
    
    public function createResource(IntegrationTester $i, Payload\ResourceBooking\Resource\Create $resource): void
    {
        #GIVEN
        $timestamp  = time();
        $resource_title = 'Testing Resource Create at ' . $timestamp;
        
        #WHEN
        $resource->setResourceName($resource_title);
        $i->sendRequest($resource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItemID = $ItemMapper->findOneByColumn('name', $resource_title)->getID();
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/item/" . $newItemID);
    }
    
    public function updateResource(IntegrationTester $i, Payload\ResourceBooking\Resource\Create $resource): void
    {
        #GIVEN
        $timestamp  = time();
        $resource_title = 'Testing Resource Update at ' . $timestamp;
        
        #WHEN
        $resource->setResourceName($resource_title);
        $i->sendRequest($resource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        
        $updatePayload=new Payload\ResourceBooking\Resource\Update($newItem);
        $updatePayload->setResourceName($resource_title);
        $updatePayload->setDescription('<p>Updated this description successfully</p>');
        $i->sendRequest($updatePayload);
        $newItemID=$newItem->getId();
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/item/" . $newItemID);
    }
    
    public function createBookingRequest(\IntegrationTester $i)
    {
        #booking item 2
        #GIVEN
        $timestamp  = time();
        
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session resource create at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $i->sendRequest($testResource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        
        $booking_name = 'Book session at ' . $timestamp;
        
        #WHEN
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);
        
        #THEN
        $i->seeResponseCodeIs(200);
    }
    
    public function updateBookingRequest(\IntegrationTester $i)
    {
        #booking item 2
    }
    
    public function aaproveBookingRequest(\IntegrationTester $i)
    {
        #booking item 2
    }
    
    public function rejectBookingRequest(\IntegrationTester $i)
    {
        #booking item 2
    }
    
    public function cancleBookingRequest(\IntegrationTester $i)
    {
        #booking item 2
    }

    
    /*
     * https://vagrant.dev.schoolbox.cloud/resource-booking/booking/25/update
     * Referer: https://vagrant.dev.schoolbox.cloud/resource-booking/booking/25/edit
     * 
     * id	25
callbackUrl	
efficacy	self
action	
name	srtstys4tys4ts4tsetsetetsertfgdxgf!!
startDate	24/04/2020+2:00pm
endDate	25/04/2020+2:00pm
notes	<p>afasfewfrawr3ra3rawer</p> 
     */
    
    //https://vagrant.dev.schoolbox.cloud/resource-booking/booking/25/approve
    //Referer: https://vagrant.dev.schoolbox.cloud/resource-booking/category/1?ids=25?ids=27?ids=28
    //Referer: https://vagrant.dev.schoolbox.cloud/resource-booking/item/16?date=24-04-2020
    //efficacy	false
    
    //https://vagrant.dev.schoolbox.cloud/resource-booking/booking/26/reject
    //efficacy	false
    
    //https://vagrant.dev.schoolbox.cloud/resource-booking/booking/25/cancel?callbackUrl=%2Fresource-booking%2Fitem%2F16
    //Referer: https://vagrant.dev.schoolbox.cloud/resource-booking/booking/25/edit
    
    /* query string
     * callbackUrl	/resource-booking/item/16
     * 
     * On calendar
     * Referer: https://vagrant.dev.schoolbox.cloud/resource-booking/item/16?date=23-04-2020
     * efficacy=self
     */
    
}