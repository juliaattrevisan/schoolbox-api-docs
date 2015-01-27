<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Link\Search;

use Schoolbox\Resource\Search\Renderer\Base;
use Schoolbox\Collection;

class Renderer extends Base {

    /**
     * Render html fragment
     */
    public function render(Collection $collection)
    {
        // TODO: Implement render() method.
        new \Alaress_Probe($collection);
    }
}