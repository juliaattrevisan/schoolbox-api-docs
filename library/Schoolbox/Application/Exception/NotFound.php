<?php
/**
 * NotFound
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\Exception;

use Schoolbox\Error\Mapper as ErrorMapper;

class NotFound extends Exception
{
    /**
     * Default error code - corresponds to message "That which you are looking for cannot be found."
     *
     * @var int
     */
    protected $code = ErrorMapper::ERROR_GENERIC_NOT_FOUND;
}
