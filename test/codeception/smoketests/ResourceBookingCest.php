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
class ResourceBookingCest
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
        #GIVEN
        $timestamp  = time();
        
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session resource update at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $i->sendRequest($testResource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        
        $booking_name = 'Book session at ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);
        
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        
        #WHEN
        $booking_name = 'Book session Updated at ' . $timestamp;
        $bookItemUpdate = new Payload\ResourceBooking\Resource\UpdateBooking($booking, $booking_name);
        $i->sendRequest($bookItemUpdate);
        
        #THEN
        $i->seeResponseCodeIs(200);
        
    }
    
    public function approveBookingRequest(\IntegrationTester $i)
    {
        #GIVEN
        $timestamp  = time();
        
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session resource approve at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $i->sendRequest($testResource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        
        $booking_name = 'Book session approved at ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);
        
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        
        $approvePayload = new Payload\ResourceBooking\Resource\ModerateBooking($booking, 
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_APPROVE);
        $i->sendRequest($approvePayload);
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");
    }
    
    public function rejectBookingRequest(\IntegrationTester $i)
    {
        #GIVEN
        $timestamp  = time();
        
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session resource reject at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $i->sendRequest($testResource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        
        $booking_name = 'Book session reject at ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);
        
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        
        $rejectPayload = new Payload\ResourceBooking\Resource\ModerateBooking($booking, 
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_REJECT);
        $rejectPayload->setFormParam('efficacy', 'false');
        $i->sendRequest($rejectPayload);
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");
    }
    
    public function cancelBookingRequest(\IntegrationTester $i)
    {
        #GIVEN
        $timestamp  = time();
        
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session resource cancel at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $i->sendRequest($testResource);
        
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        
        $booking_name = 'Book session cancel at ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);
        
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        
        $cancelPayload = new Payload\ResourceBooking\Resource\ModerateBooking($booking, 
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_CANCEL);
        $i->sendRequest($cancelPayload);
        
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");
    }
    
}