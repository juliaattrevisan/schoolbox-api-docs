<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Tests\Smoketests;

use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\ResourceBooking;
use Schoolbox\ResourceBooking\Item\ModerationType;
use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

/**
 * Tests:
 * * Approve a booking time slot request for a bookable resource.
 * * Cancel an approved booking request.
 * * Create a booking request to be moderated.
 * * Create a category of booking resources.
 * * Create a booking resource.
 * * Reject a booking request.
 * * Update the title and description of a booking request.
 * * Update the title of a category.
 * * Update the title and description of a resource.
 * * Create a booking that clashes with another booking when clash bookings are allowed (two variants)
 * * Attempt to create a booking that clashes with another booking when clash bookings are not allowed (two variants)
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

    /**
     * Hit /resource-booking/category url with POST to create a category.
     *
     * @param IntegrationTester $i
     */
    public function createCategory(IntegrationTester $i, Payload\ResourceBooking\Category\Create $category): void
    {
        #GIVEN
        $timestamp  = time();
        $category_title = 'Testing Category Create at ' . $timestamp;

        #WHEN
        $category->setCategoryTitle($category_title);
        $i->sendRequest($category);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/category");
    }

    /**
     * Hit /resource-booking/category/<id>/update with POST to edit the category title.
     * The test first creates the category using a post request, then updates it.
     *
     * @param IntegrationTester $i
     */
    public function updateCategory(IntegrationTester $i, Payload\ResourceBooking\Category\Create $category): void
    {
        #GIVEN
        $timestamp  = time();
        $category_title = 'Testing Category Update at ' . $timestamp;

        #WHEN
        $category->setCategoryTitle($category_title);
        $i->sendRequest($category);

        $CatMapper = \Schoolbox\ResourceBooking\Category\Mapper::getInstance();
        $newCat = $CatMapper->findOneByColumn('name', $category_title);

        $updatePayload = new Payload\ResourceBooking\Category\Update($newCat);
        $updatePayload->setCategoryTitle($category_title . ' Updated');
        $i->sendRequest($updatePayload);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/category");
    }

    /**
     * Hit /resource-booking/item with POST to add a booking resource item.
     *
     * @param IntegrationTester $i
     */
    public function createResource(IntegrationTester $i, Payload\ResourceBooking\Resource\Create $resource): void
    {
        #GIVEN
        $timestamp  = time();
        $resource_title = 'Testing Resource Create at ' . $timestamp;

        #WHEN
        $resource->setResourceName($resource_title);
        $i->sendRequest($resource);

        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        $newItemID = $newItem->getID();

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/item/" . $newItemID);
        $i->assertEquals(1, $newItem->getCapacity(), 'Default resource capacity is 1');
    }

    /**
     * Hit /resource-booking/item/<id>/edit with POST to edit a booking resource item.
     *
     * This test just changes the title and description.
     * The test creates a new booking resource item to edit, then tests the edit request.
     *
     * @param IntegrationTester $i
     */
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

        $updatePayload = new Payload\ResourceBooking\Resource\Update($newItem);
        $updatePayload->setResourceName($resource_title);
        $updatePayload->setDescription('<p>Updated this description successfully</p>');
        $updatePayload->setCapacity('7');
        $i->sendRequest($updatePayload);
        $newItemID = $newItem->getId();

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/resource-booking/item/" . $newItemID);
        $updatedItem = $ItemMapper->findById($newItemID);
        $i->assertEquals(7, $updatedItem->getCapacity(), 'Updated resource capacity is persisted');
    }

    public function createResourceRejectsInvalidCapacity(IntegrationTester $i): void
    {
        foreach (['0', '1000', 'abc', '1.5', ''] as $capacity) {
            $resource = new Payload\ResourceBooking\Resource\Create();
            $resource->setResourceName('Testing Resource Invalid Capacity at ' . microtime(true));
            $resource->setCapacity($capacity);

            $i->sendRequest($resource);

            $i->seeResponseCodeIs(400);
        }
    }

    /**
     * Hit /resource-booking/booking/bookItem/<ID> with POST to create a new booking request.
     *
     * The test creates a new booking resource item to send a booking request to then we test the booking request.
     *
     * @param IntegrationTester $i
     */
    public function createBookingRequest(IntegrationTester $i): void
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

        #THEN check response code and the booking status
        $i->seeResponseCodeIs(200);


        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        $bookingId = $booking->getId();

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '1');
    }

    /**
     * Hit /resource-booking/booking/bookItem/<ID> with POST to create a new booking request with a sub resource.
     *
     * The test creates a new related resource item and booking resource item to send a booking request
     * with a related resource item then we test the booking request with that related booking resource.
     *
     * @param IntegrationTester $i
     */
    public function createBookingRequestWithSubResource(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();

        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session a sub resource at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $i->sendRequest($testResource);

        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newSubresource = $ItemMapper->findOneByColumn('name', $resource_title);

        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Book session resource and sub resource create at ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $testResource->addRelatedResource($newSubresource);
        $i->sendRequest($testResource);

        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);


        $booking_name = 'Book session at ' . $timestamp;

        #WHEN
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $bookItemPayload->addRelatedResource($newSubresource->getId(), 'A sub resource notes');
        $i->sendRequest($bookItemPayload);

        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        $bookingId = $booking->getId();

        #THEN check response code and the booking status
        $i->seeResponseCodeIs(200);
        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', ['id' => $bookingId]);
        $i->assertEquals($bookingStatus, '1');
    }

    /**
     *  Hit /resource-booking/booking/<ID>/update with POST to update a current booking request.
     *
     *  The test create a new booking resource item, booking for that resource item. Then sends the update request to edit it.
     *
     * @param IntegrationTester $i
     */
    public function updateBookingRequest(IntegrationTester $i): void
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
        $bookingId = $booking->getId();

        #WHEN check response code and the booking status
        $booking_name = 'Book session Updated at ' . $timestamp;
        $bookItemUpdate = new Payload\ResourceBooking\Resource\UpdateBooking($booking, $booking_name);
        $i->sendRequest($bookItemUpdate);

        #THEN check response code and the booking status
        $i->seeResponseCodeIs(200);
        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '1');
    }

    /**
     *  Hit /resource-booking/booking/<ID>/approve with POST to approve booking request.
     *
     *  The test create a new booking resource item, booking for that resource item. Then sends the approval moderation request.
     *
     * @param IntegrationTester $i
     */
    public function approveBookingRequest(IntegrationTester $i): void
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
        $bookingId = $booking->getId();

        $approvePayload = new Payload\ResourceBooking\Resource\ModerateBooking(
            $booking,
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_APPROVE
        );
        $i->sendRequest($approvePayload);

        #THEN check response code and the booking status
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '2');
    }

    /**
     *  Hit /resource-booking/booking/<ID>/reject with POST to reject booking request.
     *
     *  The test create a new booking resource item, booking for that resource item. Then sends the reject moderation request.
     *
     * @param IntegrationTester $i
     */
    public function rejectBookingRequest(IntegrationTester $i): void
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
        $bookingId = $booking->getId();

        $rejectPayload = new Payload\ResourceBooking\Resource\ModerateBooking(
            $booking,
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_REJECT
        );
        $rejectPayload->setFormParam('efficacy', 'false');
        $i->sendRequest($rejectPayload);

        #THEN check response code and the booking status
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '3');
    }

    /**
     *  Hit /resource-booking/booking/<ID>/cancel with POST to cancel booking request.
     *
     *  The test create a new booking resource item, booking for that resource item. Then sends the cancel request.
     *
     * @param IntegrationTester $i
     */
    public function cancelBookingRequest(IntegrationTester $i): void
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

        $cancelPayload = new Payload\ResourceBooking\Resource\ModerateBooking(
            $booking,
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_CANCEL
        );
        $i->sendRequest($cancelPayload);

        #THEN
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");
    }

    /**
     *  Hit /resource-booking/booking/<ID>/approve with POST to approve booking request.
     *
     *  The test create a new booking resource item wih moderation Moderated, booking for that resource item. Then sends the approval moderation request.
     *
     * @param IntegrationTester $i
     */
    public function approveModeratedBookingRequest(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();

        $i->amGoingTo('add a Resource');
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Resource with moderation moderated by Jack Coles' . $timestamp;

        $testResource->setResourceName($resource_title);
        $userId = '22';
        $testResource->setModerator($userId);
        $i->sendRequest($testResource);

        $i->amGoingTo('alaress admin creates a booking to be moderated');
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        $booking_name = 'Book session to be approved ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);

        $i->amGoingTo('check the booking status is pending');
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        $bookingId = $booking->getId();

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '1');

        $i->amGoingTo('approve the booking');
        $approvePayload = new Payload\ResourceBooking\Resource\ModerateBooking(
            $booking,
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_APPROVE
        );
        $i->sendRequest($approvePayload);

        #THEN
        $i->amGoingTo('check the response is 303 and booking status is approved');
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '2');

        #WHEN
        $i->amGoingTo('the moderator Jack Coles creates a booking to be moderated');
        $i->impersonateUser(22);
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();

        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        $booking_title = 'Book session to be auto approved approved ' . $timestamp;
        $startDate = date('d/m/Y g:ia', strtotime('+ 5 days'));
        $endDate = date('d/m/Y g:ia', strtotime('+ 5 days + 1 hour'));
        $bookItemPayload = $bookItemPayload->constructWithDate($newItem, $booking_title, $startDate, $endDate);
        $i->sendRequest($bookItemPayload);

        $i->amGoingTo('check the booking status is approved');
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_title);
        $bookingId = $booking->getId();

        #THEN
        $i->amGoingTo('check the response is 200 and booking status is approved');
        $i->seeResponseCodeIs(200);

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '2');
    }

    /**
     *  Hit /resource-booking/booking/<ID>/approve with POST to approve booking request.
     *
     *  The test create a new booking resource item wih moderation Managed, booking for that resource item. Then sends the approval moderation request.
     *
     * @param IntegrationTester $i
     */
    public function approveManagedBookingRequest(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp  = time();

        $i->amGoingTo('add a Resource');
        $testResource = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test Resource with moderation managed ' . $timestamp;
        $testResource->setResourceName($resource_title);
        $moderationType = \Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_MANAGED;
        $testResource->setModerationTypeId((string)$moderationType);
        $userId = '22';
        $testResource->setModerator($userId);
        $i->sendRequest($testResource);

        $i->amGoingTo('create a booking to be moderated');
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);

        $booking_name = 'Managed book session to be approved ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_name);
        $i->sendRequest($bookItemPayload);

        $i->amGoingTo('check the booking status is pending');
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_name);
        $bookingId = $booking->getId();

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', ['id' => $bookingId]);
        $i->assertEquals($bookingStatus, '1');

        $i->amGoingTo('approve the booking');
        $approvePayload = new Payload\ResourceBooking\Resource\ModerateBooking(
            $booking,
            Payload\ResourceBooking\Resource\ModerateBooking::ACTION_APPROVE
        );
        $i->sendRequest($approvePayload);

        #THEN
        $i->amGoingTo('check the response is 303 and booking status is approved');
        $i->seeResponseCodeIs(303);
        $i->seeHttpHeader('location', "/");

        $bookingStatusReviewed = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', ['name' => $booking_name]);
        $i->assertEquals($bookingStatusReviewed, '2');

        #WHEN
        $i->amGoingTo('the moderator Jack Coles creates a booking to be moderated');
        $i->impersonateUser(22);
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();

        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        $booking_title = 'Managed book session to be auto approved ' . $timestamp;
        $startDate = date('d/m/Y g:ia', strtotime('+ 5 days'));
        $endDate = date('d/m/Y g:ia', strtotime('+ 5 days + 1 hour'));
        $bookItemPayload = $bookItemPayload->constructWithDate($newItem, $booking_title, $startDate, $endDate);
        $i->sendRequest($bookItemPayload);

        $i->amGoingTo('check the booking status is approved');
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_title);
        $bookingId = $booking->getId();

        #THEN
        $i->amGoingTo('check the response is 200 and booking status is approved');
        $i->seeResponseCodeIs(200);

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '2');
    }

    /**
     *  Hit /resource-booking/booking/<ID>/approve with POST to approve booking request.
     *
     *  The test create a new booking resource item wih moderation Managed and an item
     *
     * @param IntegrationTester $i
     */
    public function bookingRequestRelatedResources(IntegrationTester $i): void
    {
        #GIVEN
        $timestamp = time();

        $i->amGoingTo('add a Resource with Jack Coles as a moderator');
        $testResource   = new Payload\ResourceBooking\Resource\Create();
        $resource_title_moderated = 'Test related resource with moderation managed ' . $timestamp;
        $testResource->setResourceName($resource_title_moderated);
        $moderationType = \Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_MANAGED;
        $testResource->setModerationTypeId((string)$moderationType);
        $userId = '22';
        $testResource->setModerator($userId);
        $i->sendRequest($testResource);
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $newSubresource = $ItemMapper->findOneByColumn('name', $resource_title_moderated);


        $i->amGoingTo('add an item with a related resource');
        $testResourceRelated   = new Payload\ResourceBooking\Resource\Create();
        $resource_title = 'Test resource with a related resource ' . $timestamp;
        $testResourceRelated->setResourceName($resource_title);
        $moderationType = \Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_OPEN;
        $testResourceRelated->setModerationTypeId((string)$moderationType);
        $testResourceRelated->addRelatedResource($newSubresource);
        $i->sendRequest($testResourceRelated);

        #WHEN
        $i->amGoingTo('the alaress admin creates a booking that has Jack Coles as moderator');
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();

        $newItem = $ItemMapper->findOneByColumn('name', $resource_title_moderated);
        $booking_title = 'Managed book session to be pending ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_title);
        $i->sendRequest($bookItemPayload);

        $i->amGoingTo('check the booking status is pending');
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_title);
        $bookingId = $booking->getId();

        #THEN
        $i->amGoingTo('check the response is 200 and booking status is pending');
        $i->seeResponseCodeIs(200);

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '1');

        #WHEN
        $i->amGoingTo('the alaress admin creates a booking with moderation open and that has a related managed resource');
        $ItemMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();

        $newItem = $ItemMapper->findOneByColumn('name', $resource_title);
        $booking_title = 'Managed book session to be auto approved ' . $timestamp;
        $bookItemPayload = new Payload\ResourceBooking\Resource\BookItem($newItem, $booking_title);
        $i->sendRequest($bookItemPayload);

        $i->amGoingTo('check the booking status is approved');
        $BookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $booking = $BookingMapper->findOneByColumn('name', $booking_title);
        $bookingId = $booking->getId();

        #THEN
        $i->amGoingTo('check the response is 200 and booking status is approved');
        $i->seeResponseCodeIs(200);

        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [ 'id' => $bookingId]);
        $i->assertEquals($bookingStatus, '2');
    }

    public function capacityTwoAllowsSecondOverlappingApprovedBooking(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTestResource(
            $i,
            'Capacity 2 allows second overlapping booking',
            2,
            false,
            ModerationType\Model::TYPE_OPEN
        );
        $startDate = date('d/m/Y g:ia', strtotime('2077-02-01 10:00:00'));
        $endDate = date('d/m/Y g:ia', strtotime('2077-02-01 11:00:00'));

        $firstBooking = $this->createCapacityTestBooking($i, $resource, 'First capacity booking', $startDate, $endDate);
        $secondBooking = $this->createCapacityTestBooking($i, $resource, 'Second capacity booking', $startDate, $endDate);

        $this->assertBookingStatus($i, $firstBooking, ResourceBooking\Booking\Status\Model::STATUS_APPROVED);
        $this->assertBookingStatus($i, $secondBooking, ResourceBooking\Booking\Status\Model::STATUS_APPROVED);
    }

    public function capacityTwoRejectsThirdOverlappingBookingWhenClashesNotAllowed(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTestResource(
            $i,
            'Capacity 2 rejects third overlapping booking',
            2,
            false,
            ModerationType\Model::TYPE_OPEN
        );
        $startDate = date('d/m/Y g:ia', strtotime('2077-02-01 10:00:00'));
        $endDate = date('d/m/Y g:ia', strtotime('2077-02-01 11:00:00'));

        $this->createCapacityTestBooking($i, $resource, 'First approved capacity booking', $startDate, $endDate);
        $this->createCapacityTestBooking($i, $resource, 'Second approved capacity booking', $startDate, $endDate);
        $thirdBookingName = 'Third rejected capacity booking ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $thirdBookingName);
        $payload->setStartDate($startDate);
        $payload->setEndDate($endDate);
        $i->sendRequest($payload);

        $i->seeResponseCodeIs(400);
        $i->canSeeResponseContainsJson(['message' => 'Could not create any bookings']);
        $i->dontSeeInDatabase('resource_booking', ['name' => $thirdBookingName]);
    }

    public function capacityTwoCreatesThirdAsPendingWhenClashesAllowed(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTestResource(
            $i,
            'Capacity 2 creates third overlapping booking as pending',
            2,
            true,
            ModerationType\Model::TYPE_OPEN
        );
        $startDate = date('d/m/Y g:ia', strtotime('2077-02-01 10:00:00'));
        $endDate = date('d/m/Y g:ia', strtotime('2077-02-01 11:00:00'));

        $this->createCapacityTestBooking($i, $resource, 'First approved clash-capacity booking', $startDate, $endDate);
        $this->createCapacityTestBooking($i, $resource, 'Second approved clash-capacity booking', $startDate, $endDate);
        $thirdBooking = $this->createCapacityTestBooking($i, $resource, 'Third pending clash-capacity booking', $startDate, $endDate);

        $this->assertBookingStatus($i, $thirdBooking, ResourceBooking\Booking\Status\Model::STATUS_PENDING);
    }

    public function moderatedResourceWithCapacityAvailableCreatesPending(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTestResource(
            $i,
            'Moderated resource with spare capacity creates pending booking',
            5,
            false,
            ModerationType\Model::TYPE_MODERATED
        );
        $booking = $this->createCapacityTestBooking(
            $i,
            $resource,
            'Moderated pending capacity booking',
            date('d/m/Y g:ia', strtotime('2077-02-01 10:00:00')),
            date('d/m/Y g:ia', strtotime('2077-02-01 11:00:00'))
        );

        $this->assertBookingStatus($i, $booking, ResourceBooking\Booking\Status\Model::STATUS_PENDING);
    }

    // Clash booking tests -------------------------------------------------------------------------------------------//
    //
    // Bookings (both pending and approved) that clash with existing pending bookings are always allowed.
    // Bookings that clash with existing approved bookings are only allowed if resource.allow_clash_bookings is true,
    // and are always saved as pending.  A pending booking cannot be approved while it clashes with other approved
    // bookings.
    //
    // Info on moderation types:
    // * Moderation type 'open' means:
    //      All staff can book the resource, and the booking is automatically approved.
    //      (if clash bookings are allowed, they are saved as 'pending', otherwise they are refused)
    // * Moderation type 'managed' means:
    //      Only moderators can book the resource.  All bookings are automatically approved.
    //      (if clash bookings are allowed, they are saved as 'pending', otherwise they are refused)
    // * Moderation type 'moderated' means:
    //      All staff can request to book the resource. Booking then needs to be approved by a moderator.
    //      (if clash bookings are allowed, they are saved as 'pending', otherwise they are refused)
    //
    // Note for all moderation types, approved and pending bookings may both exist (since moderation type can change).

    /**
     * @param IntegrationTester $i
     * @return void
     */
    public function createBookingsThatClashWithApprovedBookingsVariant1(IntegrationTester $i): void
    {
        $this->createBookingsThatClashWithExistingApprovedBookings($i, false, false);
    }

    /**
     * @param IntegrationTester $i
     * @return void
     */
    public function createBookingsThatClashWithApprovedBookingsVariant2(IntegrationTester $i): void
    {
        $this->createBookingsThatClashWithExistingApprovedBookings($i, false, true);
    }

    /**
     * @param IntegrationTester $i
     * @return void
     */
    public function createBookingsThatClashWithApprovedBookingsVariant3(IntegrationTester $i): void
    {
        $this->createBookingsThatClashWithExistingApprovedBookings($i, true, false);
    }

    /**
     * @param IntegrationTester $i
     * @return void
     */
    public function createBookingsThatClashWithApprovedBookingsVariant4(IntegrationTester $i): void
    {
        $this->createBookingsThatClashWithExistingApprovedBookings($i, true, true);
    }

    /**
     * To inspect the results of this test after it has run,
     * goto https://$schoolboxDomain/resource-booking/item/$resourceId.
     * Get $resourceId from the test output (look for text "The new resource has id...").
     *
     * @param IntegrationTester $i
     * @param bool $allowClashBookings Whether new bookings are allowed to clash with pre-existing approved bookings.
     *                                 Note new bookings are always allowed to clash with pre-existing pending bookings.
     * @param bool $clashOnFirstEvent The first event is created separately to the repeat events, so it is good to test it independently.
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    private function createBookingsThatClashWithExistingApprovedBookings(
        IntegrationTester $i,
        bool $allowClashBookings,
        bool $clashOnFirstEvent,
    ): void {
        $resourceMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $eventMapper = \Schoolbox\Calendar\EventMapper::getInstance();

        // Note regarding choice of moderation type for this test
        // ------------------------------------------------------
        // We need a moderation type for which non-clash bookings will be saved as 'approved', so we can confirm that
        // clash bookings are detected and handled differently (they should be saved with status 'pending').
        // Both 'open' and 'managed' moderation types meet this criteria.  'Open' was chosen so that the user who
        // creates the clash booking does not have to be a moderator.
        //    We don't need to test other moderation types, because the behaviour of new bookings that clash with
        // existing 'approved' bookings does not depend on the moderation type of the moderation type of the new
        // booking.  Regardless of the moderation type of the new bookings, any booking that clashes with existing
        // 'approved' bookings will be:
        //  * refused if allow_clash_bookings is false.
        //  * booked with status 'pending' if allow_clash_bookings is true.
        // Note bookings that clash with existing 'pending' bookings are covered by another test (TODO write test).
        $moderationTypeId = ModerationType\Model::TYPE_OPEN; // Moderation type 'open' is used so that non-clash bookings will have status 'approved'.
        $expectedClashBookingStatusId = ResourceBooking\Booking\Status\Model::STATUS_PENDING; // This is true for any moderation type.
        $expectedNonClashBookingStatusId = ResourceBooking\Booking\Status\Model::STATUS_APPROVED; // This is true for moderation type 'open'.

        // Create a resource -----------------------------------------------------------------------------------------//

        $i->amGoingTo('create a resource with allow_clash_bookings=' . ($allowClashBookings ? 'true' : 'false'));
        $payload = new Payload\ResourceBooking\Resource\Create();
        $resourceTitle = 'Test resource created at ' . microtime(true);
        $payload->setResourceName($resourceTitle);
        $payload->setResourceCat('3'); // Support Services
        $payload->setAllowClashBookings($allowClashBookings);
        $payload->setModerationTypeId((string)$moderationTypeId);
        $i->sendRequest($payload);

        $i->amGoingTo('Check that the resource has been created');
        $i->seeResponseCodeIs(303);
        $resourceId = $resourceMapper->findOneByColumn('name', $resourceTitle)->getId();
        $i->seeHttpHeader('location', "/resource-booking/item/$resourceId");
        // Note: The resource can be fetched by sending a GET request to $i->grabHttpHeader('location').
        //       Since we know the name and the name is unique, faster to get from the database.
        $resource = $resourceMapper->findOneByColumn('name', $resourceTitle);
        $i->assertEquals(
            $allowClashBookings,
            $resource->getAllowClashBookings(),
            'Property allow_clash_bookings has the expected value'
        );
        $i->comment('The new resource has id ' . $resource->getId());

        if ($clashOnFirstEvent) {
            // Create a pre-existing booking -----------------------------------------------------------------------------//
            // (this is designed to clash with the first clash booking created below)

            $i->amGoingTo('create pre-existing booking 1 of that resource, on 2050-01-01, from 10am to 2pm');
            $preExistingBooking1Name = 'Pre-existing booking 1 ' . microtime(true);
            $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $preExistingBooking1Name);
            $payload->setFormParam('startDate', date('d/m/Y g:ia', strtotime('2050-01-01 10:00:00')));
            $payload->setFormParam('endDate', date('d/m/Y g:ia', strtotime('2050-01-01 14:00:00')));
            $i->sendRequest($payload);
            $i->seeResponseCodeIs(200);

            $i->amGoingTo('check that pre-existing booking 1 has been created');
            $booking1 = $bookingMapper->findOneByColumn('name', $preExistingBooking1Name);
            $i->comment('The new booking has id ' . $booking1->getId());
            $i->comment('The new booking uses resource ' . $booking1->getResource()->getId());
            $i->assertEquals(
                $booking1->getStatus()->getId(),
                $expectedNonClashBookingStatusId,
                "The booking has the expected statusId for non-clash bookings"
            );
        }

        // Create a pre-existing booking to clash with another clash booking created below ---------------------------//
        // (this is designed to clash with the third clash booking created below)

        $i->amGoingTo('create pre-existing booking 2 of that resource, on 2050-01-03, from 10am to 2pm');
        $preExistingBooking2Name = 'Pre-existing booking 2 ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $preExistingBooking2Name);
        $payload->setFormParam('startDate', date('d/m/Y g:ia', strtotime('2050-01-03 10:00:00')));
        $payload->setFormParam('endDate', date('d/m/Y g:ia', strtotime('2050-01-03 14:00:00')));
        $i->sendRequest($payload);
        $i->seeResponseCodeIs(200);

        $i->amGoingTo('check that the pre-existing booking 2 has been created');
        $booking2 = $bookingMapper->findOneByColumn('name', $preExistingBooking2Name);
        $i->comment('The new booking has id ' . $booking2->getId());
        $i->comment('The new booking uses resource ' . $booking2->getResource()->getId());
        $i->assertEquals(
            $booking2->getStatus()->getId(),
            $expectedNonClashBookingStatusId,
            "The booking has the expected statusId for non-clash bookings"
        );

        // Create clashing bookings (using recurrence) ---------------------------------------------------------------//

        $i->amGoingTo('Attempt to create a series of bookings, some of which will clash with the pre-existing bookings');
        $i->comment('The bookings will be on 2050-01-01, from 12pm to 4pm, repeating daily until 2050-01-04');
        $clashBookingsName = 'Clash booking ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $clashBookingsName);
        $payload->setFormParam('startDate', date('d/m/Y g:ia', strtotime('2050-01-01 12:00:00')));
        $payload->setFormParam('endDate', date('d/m/Y g:ia', strtotime('2050-01-01 16:00:00')));
        $payload->setFormParam('repeat', 'daily');
        $payload->setFormParam('repeat_until', date('d/m/Y', strtotime('2050-01-04')));
        $i->sendRequest($payload);

        if ($allowClashBookings) {
            $i->seeResponseCodeIs(200);
            $i->amGoingTo('confirm that the clash bookings have been created since they are allowed');
            $clashBookings = $bookingMapper->findByColumn('name', $clashBookingsName);
            $i->comment('Found ' . count($clashBookings) . ' clash bookings');
            $nClashBookings = $clashOnFirstEvent ? 2 : 1;
            $nApprovedBookings = 4 - $nClashBookings;
            $i->amGoingTo('confirm that the message includes the count of bookings created with each status');
            $i->canSeeResponseContainsJson(['message' => "Created 4 bookings - $nApprovedBookings approved, $nClashBookings pending"]);

            foreach ($clashBookings as $clashBooking) {
                $event = $eventMapper->findOneByColumn('id', $clashBooking->getEvent()->getId());
                $clashBookingDate = $event->getStartDateTime()->getDatabaseDate();
                $clashBookingStatusId = $clashBooking->getStatus()->getId();

                $i->comment("The clash booking on $clashBookingDate has id " . $clashBooking->getId());
                $i->assertEquals(
                    $clashBooking->getResource()->getId(),
                    $resource->getId(),
                    "The clash booking on $clashBookingDate uses resource {$resource->getId()} as expected"
                );

                switch ($clashBookingDate) {
                    case '2050-01-01':
                        if ($clashOnFirstEvent) {
                            $i->assertEquals(
                                $clashBookingStatusId,
                                $expectedClashBookingStatusId,
                                "The clash booking on $clashBookingDate has the expected statusId for clash bookings"
                            );
                        } else {
                            $i->assertEquals(
                                $clashBookingStatusId,
                                $expectedNonClashBookingStatusId,
                                "The clash booking on $clashBookingDate has the expected statusId for non-clash bookings"
                            );
                        }
                        break;
                    case '2050-01-03':
                        $i->assertEquals(
                            $clashBookingStatusId,
                            $expectedClashBookingStatusId,
                            "The clash booking on $clashBookingDate has the expected statusId for clash bookings"
                        );
                        break;
                    case '2050-01-02': // Fall through.
                    case '2050-01-04':
                        $i->assertEquals(
                            $clashBookingStatusId,
                            $expectedNonClashBookingStatusId,
                            "The clash booking on $clashBookingDate has the expected statusId for non-clash bookings"
                        );
                        break;
                    default:
                        $i->assertEquals(true, false, 'Unexpected date for clash booking');
                }
            }
        } else {
            // If clash bookings are not allowed
            $i->amGoingTo('confirm that the clash bookings were not created since they are not allowed');
            $i->seeResponseCodeIs(400);
        }
    }

    /**
     * @param IntegrationTester $i
     * @return void
     */
    public function createBookingThatClashesWithPendingBookingVariant1(IntegrationTester $i): void
    {
        $this->createBookingThatClashesWithExistingPendingBooking($i, false);
    }

    /**
     * @param IntegrationTester $i
     * @return void
     */
    public function createBookingThatClashesWithPendingBookingVariant2(IntegrationTester $i): void
    {
        $this->createBookingThatClashesWithExistingPendingBooking($i, true);
    }

    /**
     * To inspect the results of this test after it has run,
     * * goto https://$schoolboxDomain/resource-booking/item/$resourceId.
     * * Get $resourceId from the test output (look for text "The new resource has id...").
     * *
     * @param IntegrationTester $i
     * @param bool $allowClashBookings
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    private function createBookingThatClashesWithExistingPendingBooking(IntegrationTester $i, bool $allowClashBookings): void
    {
        $resourceMapper = ResourceBooking\Item\Mapper::getInstance();
        $bookingMapper = ResourceBooking\Booking\Mapper::getInstance();

        // Create a resource -----------------------------------------------------------------------------------------//

        $i->amGoingTo('create a resource with allow_clash_bookings=' . ($allowClashBookings ? 'true' : 'false'));
        $payload = new Payload\ResourceBooking\Resource\Create();
        $resourceTitle = 'Test resource created at ' . microtime(true);
        $payload->setResourceName($resourceTitle);
        $payload->setResourceCat('3'); // Support Services
        $payload->setAllowClashBookings($allowClashBookings);
        $payload->setModerationTypeId((string)ModerationType\Model::TYPE_MANAGED); // Start with moderation type 'managed' so we can create 'pending' bookings.
        $i->sendRequest($payload);

        $i->amGoingTo('Check that the resource has been created');
        $i->seeResponseCodeIs(303);
        $resourceId = $resourceMapper->findOneByColumn('name', $resourceTitle)->getId();
        $i->seeHttpHeader('location', "/resource-booking/item/$resourceId");
        // Note: The resource can be fetched by sending a GET request to $i->grabHttpHeader('location').
        //       Since we know the name and the name is unique, faster to get from the database.
        $resource = $resourceMapper->findOneByColumn('name', $resourceTitle);
        $i->assertEquals(
            $allowClashBookings,
            $resource->getAllowClashBookings(),
            'Property allow_clash_bookings has the expected value'
        );
        $i->comment('The new resource has id ' . $resource->getId());

        // Create a pre-existing 'pending' booking to clash with another clash booking created below -----------------//
        // (this is designed to clash with the third clash booking created below)

        $i->amGoingTo('create a pre-existing booking of that resource, on 2050-01-01, from 10am to 2pm');
        $preExistingBookingName = 'Pre-existing booking ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $preExistingBookingName);
        $payload->setFormParam('startDate', date('d/m/Y g:ia', strtotime('2050-01-01 10:00:00')));
        $payload->setFormParam('endDate', date('d/m/Y g:ia', strtotime('2050-01-01 14:00:00')));
        $i->sendRequest($payload);
        $i->seeResponseCodeIs(200);

        $i->amGoingTo('check that the pre-existing booking has been created');
        $booking = $bookingMapper->findOneByColumn('name', $preExistingBookingName);
        $i->comment('The new booking has id ' . $booking->getId());
        $i->comment('The new booking uses resource ' . $booking->getResource()->getId());
        $i->assertEquals(
            $booking->getStatus()->getId(),
            ResourceBooking\Booking\Status\Model::STATUS_PENDING,
            "The booking has status 'pending' as expected"
        );

        // Create clashing booking with status 'pending' -------------------------------------------------------------//

        $i->amGoingTo('Create a booking that clashes with the pre-existing booking');
        $i->comment('The booking will be on 2050-01-01, from 12pm to 4pm');
        $clashBookingsName = 'Clash booking ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $clashBookingsName);
        $payload->setFormParam('startDate', date('d/m/Y g:ia', strtotime('2050-01-01 12:00:00')));
        $payload->setFormParam('endDate', date('d/m/Y g:ia', strtotime('2050-01-01 16:00:00')));
        $i->sendRequest($payload);
        $i->amGoingTo('confirm that the message indicates a pending booking was created');
        $i->canSeeResponseContainsJson(['message' => 'Created one pending booking']);

        $i->amGoingTo('confirm that the clash booking has been created');
        $clashBooking = $bookingMapper->findOneByColumn('name', $clashBookingsName);
        $i->comment("The clash booking has id " . $clashBooking->getId());
        $i->assertEquals(
            $clashBooking->getResource()->getId(),
            $resource->getId(),
            "The clash booking uses resource {$resource->getId()} as expected"
        );
        $i->assertEquals(
            $clashBooking->getStatus()->getId(),
            ResourceBooking\Booking\Status\Model::STATUS_PENDING,
            "The clash booking has status 'pending' as expected"
        );

        // Create clashing booking with status 'approved' ------------------------------------------------------------//

        $i->amGoingTo("Set the resource's moderation type to 'open'");
        $resource->setModerationType(new ModerationType\Model()->setId(ModerationType\Model::TYPE_OPEN));
        $resourceMapper->save($resource);
        $i->assertEquals(
            $resource->getModerationType()->getId(),
            ModerationType\Model::TYPE_OPEN,
            "The resource now has moderation type 'open' as expected"
        );
        $i->amGoingTo('Create a booking that clashes with the pre-existing booking');
        $i->comment('The booking will be on 2050-01-01, from 12pm to 4pm');
        $clashBookingsName = 'Clash booking ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $clashBookingsName);
        $payload->setFormParam('startDate', date('d/m/Y g:ia', strtotime('2050-01-01 12:00:00')));
        $payload->setFormParam('endDate', date('d/m/Y g:ia', strtotime('2050-01-01 16:00:00')));
        $i->sendRequest($payload);
        $i->amGoingTo('confirm that the message indicates an approved booking was created');
        $i->canSeeResponseContainsJson(['message' => 'Created one approved booking']);

        $i->amGoingTo('confirm that the clash booking has been created');
        $clashBooking = $bookingMapper->findOneByColumn('name', $clashBookingsName);
        $i->comment("The clash booking has id " . $clashBooking->getId());
        $i->assertEquals(
            $clashBooking->getResource()->getId(),
            $resource->getId(),
            "The clash booking uses resource {$resource->getId()} as expected"
        );
        $i->assertEquals(
            $clashBooking->getStatus()->getId(),
            ResourceBooking\Booking\Status\Model::STATUS_APPROVED,
            "The clash booking has status 'approved' as expected"
        );
    }

    private function createCapacityTestResource(
        IntegrationTester $i,
        string $titlePrefix,
        int $capacity,
        bool $allowClashBookings,
        int $moderationTypeId
    ): ResourceBooking\Item\Model {
        $resourceMapper = ResourceBooking\Item\Mapper::getInstance();
        $resourceTitle = $titlePrefix . ' ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\Create();
        $payload->setResourceName($resourceTitle);
        $payload->setResourceCat('3');
        $payload->setCapacity((string)$capacity);
        $payload->setAllowClashBookings($allowClashBookings);
        $payload->setModerationTypeId((string)$moderationTypeId);

        $i->sendRequest($payload);

        $i->seeResponseCodeIs(303);
        $resource = $resourceMapper->findOneByColumn('name', $resourceTitle);
        $i->seeHttpHeader('location', '/resource-booking/item/' . $resource->getId());
        $i->assertEquals($capacity, $resource->getCapacity(), 'Resource capacity has the expected value');
        $i->assertEquals(
            $allowClashBookings,
            $resource->getAllowClashBookings(),
            'Property allow_clash_bookings has the expected value'
        );

        return $resource;
    }

    private function createCapacityTestBooking(
        IntegrationTester $i,
        ResourceBooking\Item\Model $resource,
        string $titlePrefix,
        string $startDate,
        string $endDate
    ): ResourceBooking\Booking\Model {
        $bookingMapper = ResourceBooking\Booking\Mapper::getInstance();
        $bookingName = $titlePrefix . ' ' . microtime(true);
        $payload = new Payload\ResourceBooking\Resource\BookItem($resource, $bookingName);
        $payload->setStartDate($startDate);
        $payload->setEndDate($endDate);

        $i->sendRequest($payload);
        $i->seeResponseCodeIs(200);

        return $bookingMapper->findOneByColumn('name', $bookingName);
    }

    private function assertBookingStatus(
        IntegrationTester $i,
        ResourceBooking\Booking\Model $booking,
        int $expectedStatusId
    ): void {
        $bookingStatus = $i->grabFromDatabase('resource_booking', 'resource_booking_status_id', [
            'id' => $booking->getId(),
        ]);
        $i->assertEquals((string)$expectedStatusId, $bookingStatus);
    }
}
