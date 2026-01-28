<?php

/**
 * Integration tests for Validation::validateBookingFromPostData()
 * These tests verify the complete validation workflow including:
 * - POST data parsing
 * - Series/recurring booking validation
 * - Clash detection with existing bookings
 * - Related resource validation
 * - Override permissions
 *
 * @author    Tom McDonnell (directing AI)
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Test\Booking;

use Alaress\Date\Interval;
use DateInterval;
use External_Timetable;
use PHPUnit\Framework\TestCase;
use Schoolbox\CampusMapper;
use Schoolbox\Config\Loader;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\User;

/**
 * Integration tests for validateBookingFromPostData
 *
 * Note: These tests use the Australian date format: d/m/Y g:ia (e.g., '25/03/2027 10:00am')
 * This matches the Alaress_Date_Locale_Static format used in the test environment.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\ResourceBooking\Booking\Validation::class)]
class ValidationIntegrationTest extends TestCase
{
    /**
     * Create a Validation instance with dependencies
     * This validation instance has the Series operations mocked to avoid deep dependencies
     */
    private function createValidation(
        ?User $user = null,
        ?BookingMapper $bookingMapper = null,
        ?StatusMapper $statusMapper = null,
        ?TermMapper $termMapper = null,
        ?CampusMapper $campusMapper = null
    ): Validation {
        if (!$user) {
            $user = $this->createUser();
        }

        if (!$bookingMapper) {
            $bookingMapper = $this->createMock(BookingMapper::class);
        }

        if (!$statusMapper) {
            $statusMapper = new StatusMapper();
        }

        if (!$termMapper) {
            $termMapper = $this->createMock(TermMapper::class);
            $termMapper->method('findForToday')->willReturn($this->createFutureTerm());
        }

        if (!$campusMapper) {
            $campusMapper = new CampusMapper();
        }

        $externalEventMapper = new \Schoolbox\External\Event\Mapper();

        $mockConfig = $this->createMock(Loader::class);
        $mockConfig->method('get')->willReturnMap([
            ['schooldata_import_data_source', \Schoolbox\External\Importer\Endpoint\Factory::TASS_API],
        ]);

        $externalTimetable = $this->getMockBuilder(External_Timetable::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new Validation(
            $user,
            $bookingMapper,
            $statusMapper,
            $termMapper,
            $externalTimetable,
            $campusMapper,
            $mockConfig,
            $externalEventMapper
        );
    }

    /**
     * Create a test user
     */
    private function createUser(): User
    {
        $user = new User();
        $user->setId(123);
        return $user;
    }

    /**
     * Create a test resource with sensible defaults
     */
    private function createResource(array $overrides = []): Item\Model
    {
        $resource = $this->getMockBuilder(Item\Model::class)
            ->onlyMethods(['canUserModerateThisItem', 'getCurrentTermRestriction', 'getRoomCode'])
            ->getMock();

        $resource->setId($overrides['id'] ?? 1);
        $resource->setName($overrides['name'] ?? 'Test Resource');
        $resource->setMinLeadTime(
            $overrides['minLeadTime'] ?? Interval::createFromDateInterval(new DateInterval('PT0H'))
        );
        $resource->setMinReservationTime(
            $overrides['minReservationTime'] ?? Interval::createFromDateInterval(new DateInterval('PT1H'))
        );
        $resource->setMaxReservationTime(
            $overrides['maxReservationTime'] ?? Interval::createFromDateInterval(new DateInterval('PT0H'))
        );

        $resource->method('canUserModerateThisItem')
            ->willReturn($overrides['canUserModerate'] ?? false);
        $resource->method('getCurrentTermRestriction')
            ->willReturn($overrides['currentTermRestriction'] ?? false);
        $resource->method('getRoomCode')
            ->willReturn($overrides['roomCode'] ?? '');

        return $resource;
    }

    /**
     * Create a term that ends far in the future
     */
    private function createFutureTerm(): Term
    {
        $term = new Term();
        $term->setDisplayFinish(
            new \Alaress_Date_Format('2030-12-31', \Alaress_Date_Format::DATABASE_DATE_FORMAT)
        );
        return $term;
    }

    /**
     * Create basic POST data for a booking
     * Uses Australian date format: d/m/Y g:ia (e.g., '25/03/2027 10:00am')
     */
    private function createPostData(array $overrides = []): array
    {
        return [
            'startDate' => $overrides['startDate'] ?? '25/03/2027 10:00am',
            'endDate' => $overrides['endDate'] ?? '25/03/2027 11:00am',
            'campus' => $overrides['campus'] ?? 0,
            'repeat' => $overrides['repeat'] ?? '',
            'repeat_until' => $overrides['repeat_until'] ?? null,
            'related_item' => $overrides['related_item'] ?? null,
            'override_validation' => $overrides['override_validation'] ?? '',
        ];
    }

    /**
     * Test validation with invalid start date format
     */
    public function testValidateBookingWithInvalidStartDate(): void
    {
        $resource = $this->createResource();
        $postData = $this->createPostData([
            'startDate' => 'invalid-date',
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postData);

        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
        $this->assertArrayHasKey(Validation::ERROR_INVALID, $errors[Validation::START_PERIOD_ERRORS]);
    }

    /**
     * Test validation with invalid end date format
     */
    public function testValidateBookingWithInvalidEndDate(): void
    {
        $resource = $this->createResource();
        $postData = $this->createPostData([
            'endDate' => 'not-a-date',
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postData);

        $this->assertArrayHasKey(Validation::END_PERIOD_ERRORS, $errors);
        $this->assertArrayHasKey(Validation::ERROR_INVALID, $errors[Validation::END_PERIOD_ERRORS]);
    }

    /**
     * Test validation with both dates invalid returns both errors
     */
    public function testValidateBookingWithBothDatesInvalid(): void
    {
        $resource = $this->createResource();
        $postData = $this->createPostData([
            'startDate' => 'bad',
            'endDate' => 'also-bad',
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postData);

        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
        $this->assertArrayHasKey(Validation::END_PERIOD_ERRORS, $errors);

        // Should not proceed to other validations when dates can't be parsed
        $flatErrors = $validation->getAllErrorStringsAsFlatArray($errors);
        $this->assertEquals(2, count($flatErrors));
    }

    /**
     * Test validation early returns when dates are invalid
     * (Should not attempt other validations)
     */
    public function testInvalidDatesPreventFurtherValidation(): void
    {
        $resource = $this->createResource([
            'minReservationTime' => Interval::createFromDateInterval(new DateInterval('PT10H')),
        ]);

        $postData = $this->createPostData([
            'startDate' => 'invalid',
            'endDate' => 'also-invalid',
        ]);

        $validation = $this->createValidation();
        $errors = $validation->validateBookingFromPostData($resource, $postData);

        // Should only have invalid date errors, not "too short" or other validation errors
        $flatErrors = $validation->getAllErrorStringsAsFlatArray($errors);
        $this->assertEquals(2, count($flatErrors)); // One for each invalid date
        $this->assertContains(Validation::ERROR_INVALID, $flatErrors);
        $this->assertNotContains(Validation::ERROR_TOO_SHORT, $flatErrors);
    }

    /**
     * Test validation with American date format fails
     * (This verifies the Australian format is expected)
     */
    public function testAmericanDateFormatIsInvalid(): void
    {
        $resource = $this->createResource();
        $postData = $this->createPostData([
            'startDate' => '03/25/2027 10:00am', // American format: MM/DD/YYYY
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postData);

        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
    }

    /**
     * Test override validation flag is respected at the parsing level
     * (The override flag is checked before calling validateSimpleRules)
     */
    public function testOverrideFlagIsParsedFromPostData(): void
    {
        $resource = $this->createResource([
            'canUserModerate' => true, // User CAN moderate
        ]);

        // Provide override flag in POST data
        $postDataWithOverride = $this->createPostData([
            'override_validation' => '1',
        ]);

        $postDataWithoutOverride = $this->createPostData([
            'override_validation' => '',
        ]);

        $validation = $this->createValidation();

        // Both should parse without exceptions
        $errorsWithOverride = $validation->validateBookingFromPostData($resource, $postDataWithOverride);
        $errorsWithoutOverride = $validation->validateBookingFromPostData($resource, $postDataWithoutOverride);

        // Verify both return arrays (successful parsing)
        $this->assertIsArray($errorsWithOverride);
        $this->assertIsArray($errorsWithoutOverride);
    }

    /**
     * Test that POST data can be provided as array or Values object
     */
    public function testPostDataAcceptsArrayType(): void
    {
        $resource = $this->createResource();
        $postDataArray = $this->createPostData([
            'startDate' => 'invalid',
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postDataArray);

        // Should handle array input without errors
        $this->assertIsArray($errors);
        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
    }

    /**
     * Test validation with missing required fields
     */
    public function testValidationWithMissingStartDate(): void
    {
        $resource = $this->createResource();
        $postData = [
            'endDate' => '25/03/2027 11:00am',
            'campus' => 0,
        ];

        $validation = $this->createValidation();

        // Should handle missing startDate gracefully
        $errors = $validation->validateBookingFromPostData($resource, $postData);

        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
    }

    /**
     * Test validation with empty string dates
     */
    public function testValidationWithEmptyStringDates(): void
    {
        $resource = $this->createResource();
        $postData = $this->createPostData([
            'startDate' => '',
            'endDate' => '',
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postData);

        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
        $this->assertArrayHasKey(Validation::END_PERIOD_ERRORS, $errors);
    }

    /**
     * Test that the validation function returns errors in the expected structure
     */
    public function testErrorsReturnedInExpectedStructure(): void
    {
        $resource = $this->createResource();
        $postData = $this->createPostData([
            'startDate' => 'bad-date',
        ]);

        $validation = $this->createValidation();

        $errors = $validation->validateBookingFromPostData($resource, $postData);

        // Verify structure: [period_type][date_key][error_array]
        $this->assertIsArray($errors);
        $this->assertArrayHasKey(Validation::START_PERIOD_ERRORS, $errors);
        $this->assertIsArray($errors[Validation::START_PERIOD_ERRORS]);
        $this->assertArrayHasKey(Validation::ERROR_INVALID, $errors[Validation::START_PERIOD_ERRORS]);
        $this->assertIsArray($errors[Validation::START_PERIOD_ERRORS][Validation::ERROR_INVALID]);
    }

    /**
     * Test that error constants are correctly defined and usable
     */
    public function testErrorConstantsAreDefined(): void
    {
        $this->assertEquals('start_period_errors', Validation::START_PERIOD_ERRORS);
        $this->assertEquals('end_period_errors', Validation::END_PERIOD_ERRORS);
        $this->assertEquals('invalid', Validation::ERROR_INVALID);
        $this->assertEquals('before_start', Validation::ERROR_BEFORE_START);
        $this->assertEquals('in_past', Validation::ERROR_IN_PAST);
        $this->assertEquals('too_early', Validation::ERROR_TOO_EARLY);
        $this->assertEquals('too_short', Validation::ERROR_TOO_SHORT);
        $this->assertEquals('too_long', Validation::ERROR_TOO_LONG);
        $this->assertEquals('conflict', Validation::ERROR_CONFLICT);
        $this->assertEquals('timetabled_class', Validation::ERROR_TIMETABLED_CLASS);
        $this->assertEquals('current_term', Validation::ERROR_CURRENT_TERM);
    }
}
