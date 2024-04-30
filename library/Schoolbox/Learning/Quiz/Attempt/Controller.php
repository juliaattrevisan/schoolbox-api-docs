<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Quiz\Attempt;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * Update the details of an attempt
     *
     * @param string $learnosityReference
     * @return void
     */
    public function getModify(string $learnosityReference): void
    {
        new \Alaress_Probe('get modify');
    }


    /**
     * Update the details of an attempt by superimposing a POST array's values
     */
    public function postModify(string $learnosityReference): void
    {
        new \Alaress_Probe('post modify');
    }
}
