<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Storage;

interface TypeHandlerInterface {

    /**
     * @param string $id
     * @return bool
     */
    public function matches($id);


    /**
     * @param string $id
     * @return Storage
     */
    public function fetch($id);

} 