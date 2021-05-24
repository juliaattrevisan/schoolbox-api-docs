<?php

/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain;

abstract class Base
{

    /**
     * Result data
     *
     * @var null
     */
    protected $result = null;

    /**
     * Start analysing
     *
     * Try until one of the strategies matches
     *
     * @param  Strategy\Internal\Base[] $responsibilities
     * @return mixed
     */
    protected function start($responsibilities)
    {
        if (count($responsibilities)) {
            for ($i = 0; $i < count($responsibilities); $i++) {
                $responsibilities[$i]->setNext($responsibilities[$i + 1]);
            }

            $this->setResult($responsibilities[0]->test());
        }
    }

    /* Getters/Setters
     */

    /**
     * Set result
     *
     * @param null $result
     */
    protected function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Get result
     *
     * @return null
     */
    public function getResult()
    {
        return $this->result;
    }
}
