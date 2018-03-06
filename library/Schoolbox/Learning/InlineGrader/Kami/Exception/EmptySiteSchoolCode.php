<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

use RuntimeException;

/**
 * Generated when we attempt to create a view session but this SB instance does not have
 * a site_school_code configured.
 */
class EmptySiteSchoolCode extends RuntimeException implements IKamiException
{

}
