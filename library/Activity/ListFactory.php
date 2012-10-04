<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Activity_ListFactory {

    /**
     * @param $data
     * @param array $config
     * @return Activity_ListAbstract|null
     */
    public function buildList($data, $config = array()) {
        if($data instanceof Iterator) {
            return new Activity_ListIterator($data, $config);
        }
        elseif(is_array($data)) {
            return new Activity_ListArray($data, $config);
        }
        else {
            return null;
        }
    }
}
