<?php

/**
 * Interface for configuration loader classes.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

interface IConfigLoader
{

    /**
     * Return the value of the given config var
     *
     * @param string $var
     * @return mixed
     */
    public function get($var);


    /**
     * Drop any cached config
     *
     * @return void
     */
    public function dropCache();
}
