<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;

/**
 * Interface for output handling classes.
 *
 * At their most basic, they must implement an `output()` method.
 *
 * @package Alaress\Web\Output
 */
interface OutputInterface
{
    /**
     * Outputs a response and its headers.
     *
     * @param Response $response
     */
    public function output(Response $response);
}
