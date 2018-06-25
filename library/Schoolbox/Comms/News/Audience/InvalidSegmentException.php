<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use UnexpectedValueException;

/**
 * Thrown if an Audience Segment is invalid.
 * Invalid segments should not be persisted to the database.
 * They should also be ignored when mapping articles to audience users and vice-versa.
 */
class InvalidSegmentException extends UnexpectedValueException
{

}
