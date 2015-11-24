<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\WebDav\Rules;

use Alaress\Rules\Rule as Super;

class Rule extends Super
{

    /**
     * @return array
     */
    public function getEffect()
    {
        $empty = array(
            'baseUri'  => '',
            'username' => '',
            'password' => '',
            'paths'    => array()
        );

        return array_merge($empty, call_user_func($this->effect));
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        $pattern   = $this->negate ? "IF NOT (%s)\nTHEN %s" : "IF %s\nTHEN %s";
        $condition = implode("\nAND ", array_map(function ($cond) {
                    return $cond->getSummary();
                }, $this->getConditions()));
        $effect    = $this->getEffect();

        $mapped = array_map(function ($k, $v) {
                return $v ? "$k = " . (is_array($v) ? "[\n  " . implode(",\n  ", $v) . "\n]" : $v) : null;
            }, array_keys($effect), $effect);

        $filtered = array_filter($mapped);

        return sprintf($pattern, $condition, implode("\nAND ", $filtered));
    }


    /**
     * @return string
     */
    public function getHtmlSummary()
    {
        $summary = preg_replace('`([A-Z=]+ )`','<span class="ctrl $1">$1</span>', $this->getSummary());

        return $summary;
    }

} 