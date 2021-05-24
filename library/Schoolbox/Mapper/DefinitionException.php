<?php

/**
 * Exception for when a mapper has been defined improperly.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper;

class DefinitionException extends \LogicException
{
    protected $message = 'Mapper defined improperly';
}
