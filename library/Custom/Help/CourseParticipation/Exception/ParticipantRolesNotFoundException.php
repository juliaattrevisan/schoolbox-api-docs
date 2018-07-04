<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Help\CourseParticipation\Exception;

/**
 * Thrown when the expected SB HELP participant roles are not found on the SHAPS server
 */
class ParticipantRolesNotFoundException extends \UnexpectedValueException
{

}
