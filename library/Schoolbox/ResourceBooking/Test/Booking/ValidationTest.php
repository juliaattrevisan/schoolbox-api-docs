<?php
/**
 * Created by PhpStorm.
 * User: nat
 * Date: 4/10/17
 * Time: 11:05 AM
 */

namespace Schoolbox\ResourceBooking\Test;

use DateTime;
use Schoolbox\ResourceBooking\Booking\Validation;


class ValidationTest extends \PHPUnit_Framework_TestCase
{

    public function testValidateBeforeTime()
    {
        $validation = new Validation();
        $startDate = new DateTime('2017-01-01T00:00:00');
        $endDate = new DateTime('2017-01-01T00:00:10');
        $errors = $validation->validateBeforeTime($startDate, $endDate);
        $this->assertCount(0, $errors);
    }

    public function testValidateBeforeTimeError()
    {
        $validation = new Validation();
        $startDate = new DateTime('2017-01-01T00:00:10');
        $endDate = new DateTime('2017-01-01T00:00:00');
        $errors = $validation->validateBeforeTime($startDate, $endDate);
        $this->assertCount(1, $errors);
    }
}