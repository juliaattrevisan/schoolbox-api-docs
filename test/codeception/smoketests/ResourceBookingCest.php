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
    
    public function createResource(IntegrationTester $i, Payload\ResourceBooking\Resource\Create $resource): void
    {
        #GIVEN
        $timestamp  = time();
        $resource_title = 'Testing Resource Create at ' . $timestamp;
        
        #WHEN
        $resource->setResourceName($resource_title);
        $resource->setDescription('<p>Testing Description</p>');
        $resource->setMinLeadTime('5','1','2');
        $resource->setAllowedRole('1', true);
        $resource->setAllowedRole('2', true);
        $resource->setResourceCat('1');
        $resource->setModerationTypeId('1');
        $i->sendRequest($resource);
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $itemID = $ItemMapper->findOneByColumn('name', $resource_title)->getID();
             
        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/item/" . $itemID);
    }
}