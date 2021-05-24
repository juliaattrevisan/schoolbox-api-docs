<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\CourseParticipation\Exception;

/**
 * Thrown when the expected SB HELP participant roles are not found on the SHAPS server
 */
class ParticipantRolesNotFoundException extends \UnexpectedValueException
{

}
