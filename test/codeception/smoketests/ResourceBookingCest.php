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
}