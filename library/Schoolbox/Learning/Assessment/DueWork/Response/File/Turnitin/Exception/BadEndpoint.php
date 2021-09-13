<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Exception;

/**
 * The endpoint you specified doesn't exist. Check that you formatted
 * your params and that you access an extant endpoint as per
 * https://developers.turnitin.com/docs/tca#introduction
 */
class BadEndpointException extends \InvalidArgumentException
{

}
