<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

/**
 * Generated when a response from Kami's servers cannot be decoded
 */
class ResponseDecodeException extends \RuntimeException implements IKamiException
{

}
