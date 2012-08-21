<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Homepage extends Generic_Object {

    public function getColours() {
        $def = array(
            'left_head_font',
            'left_head_bg',
            'left_font',
            'left_bg_primary',
            'left_bg_secondary',
            'left_link',
            'left_hover',
            'right_head_font',
            'right_head_bg',
            'right_font',
            'right_bg_primary',
            'right_bg_secondary',
            'right_link',
            'right_hover',
            'top_head_font',
            'top_head_bg',
            'top_font',
            'top_bg_primary',
            'top_bg_secondary',
            'top_link',
            'top_hover'
        );

        $colours = array();

        foreach($def as $c) {
            $colours[$c] = $this->get($c);
        }

        return $colours;
    }
}
