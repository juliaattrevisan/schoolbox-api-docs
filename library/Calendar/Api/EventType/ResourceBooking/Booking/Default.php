<?php

/**
 * Default event source for resource bookings - an alias for
 * Calendar_Api_EventType_ResourceBooking_Booking_ForUser.
 *
 * This alias exists because the calendar API expects a class called
 * "Calendar_Api_EventType_{type}_{subType}_Default".
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Calendar_Api_EventType_ResourceBooking_Booking_Default extends Calendar_Api_EventType_ResourceBooking_Booking_ForUser
{
}
