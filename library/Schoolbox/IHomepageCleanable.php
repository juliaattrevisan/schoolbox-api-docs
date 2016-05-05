<?php
/**
 * Interface for Homepage cleanable components
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   2016 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

interface IHomepageCleanable {

    /**
     * Soft-deletes content for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid);
}