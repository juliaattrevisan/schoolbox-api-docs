<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

/**
 * Thrown if attempting to create a \Schoolbox\Learning\InlineGrader\Kami\Document
 * with an invalid status.
 */
class InvalidStatusException extends \UnexpectedValueException implements IKamiException
{

}
