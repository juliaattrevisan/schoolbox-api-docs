<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Exception;

/**
 * Generated when system attempts a Kami operation but the API key is not configured.
 */
class EmptyApiKeyException extends \RuntimeException implements IKamiException
{

}
