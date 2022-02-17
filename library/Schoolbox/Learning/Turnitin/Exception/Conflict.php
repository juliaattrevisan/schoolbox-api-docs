<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Exception;

/**
 * This API request raised a 409 Conflict response.
 *
 * A common cause for this is generating a similarity report
 * (https://developers.turnitin.com/docs/tca#generate-similarity-report) if the
 * submission's state is not complete.
 */
class Conflict extends Exception
{

}
