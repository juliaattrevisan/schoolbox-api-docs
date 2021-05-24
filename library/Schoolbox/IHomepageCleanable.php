<?php

/**
 * Interface for Homepage cleanable components
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

interface IHomepageCleanable
{

    /**
     * Soft-deletes content for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid);
}
