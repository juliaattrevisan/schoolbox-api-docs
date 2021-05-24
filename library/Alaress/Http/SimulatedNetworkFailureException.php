<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Http;

use GuzzleHttp\Exception\ClientException;
use Schoolbox\Learning\InlineGrader\Kami\Exception\IKamiException;

class SimulatedNetworkFailureException extends ClientException implements IKamiException
{

}
