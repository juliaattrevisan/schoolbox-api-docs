<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Exception;

/**
 * Turnitin gave you 403 Forbidden, which means your credentials
 * were either incorrect, turnitin locked you out, or your payload
 * structure is wrong,
 *
 * Check it on https://developers.turnitin.com/docs/tca#error-responses
 */
class Credentials extends \RuntimeException
{

}
