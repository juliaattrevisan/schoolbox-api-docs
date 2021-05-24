<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

use GuzzleHttp\Exception\ClientException;

/**
 * Thrown when the requested object is not found because it does not exist, as opposed
 * to an invalid API route
 */
class NotFound extends ClientException implements IKamiException
{

}
