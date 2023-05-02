<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Preview\Chain\Strategy\Base as StrategyBase;
use Schoolbox\Resource\Storage;

/**
 * Class Base
 *
 * @package Schoolbox\Preview\Chain\Strategy\Internal
 */
abstract class Base extends StrategyBase
{
    /**
     * Constructor
     *
     * @param string                $url  A URL to test.
     * @param Storage\IStorage|null $item A storage model to test, if any.
     */
    public function __construct(protected string $url, protected ?Storage\IStorage $item)
    {
    }
}
