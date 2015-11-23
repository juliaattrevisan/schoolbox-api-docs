<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rules;

use Alaress\Rules\Condition;
use Alaress\Rules\Lang\Construct;
use Alaress\Rules\Rule as Super;
use Alaress\Rules\Token;
use Schoolbox\User;

class Rule extends Super
{

    /**
     * @return array
     */
    public function getEffect()
    {
        return call_user_func($this->effect);
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        $conditions = $this->getConditions();

        if (count($conditions)) {
            $pattern   = $this->negate ? "IF NOT (%s)\nTHEN %s" : "IF %s\nTHEN %s";
            $condition = implode("\nAND ", array_map(function ($condition) {
                /** @var Condition $condition */
                return $condition->getSummary();
            }, $conditions));

        } else {
            $pattern   = $this->negate ? "NEVER %s%s" : "ALWAYS %s%s";
            $condition = '';
        }

        $effect = $this->getEffect();

        $mapped = array_map(function ($k, $v) {

            if ($v instanceof Construct) {
                // apply the construct
                return $k . $v->getSummary();

            } else {
                $v = $this->getObjectOutput($v);

                if ($v === null) {
                    return "$k = NULL";
                } elseif ($v === false) {
                    return "$k = FALSE";
                } elseif ($v === true) {
                    return "$k = TRUE";
                } else {
                    return "$k = " . (is_array($v) ? "[\n  " . implode(",\n  ", $v) . "\n]" : $v);
                }
            }

        }, array_keys($effect), $effect);

        $filtered = array_filter($mapped);

        return sprintf($pattern, $condition, implode("\nAND ", $filtered));
    }


    /**
     * @return string
     */
    public function getHtmlSummary()
    {
        $formatting = [
            '`(=|[A-Z][A-Z]+)(\s|$)`'    => '<span class="ctrl $1">$1</span>$2',
            Token\Tokenizer::TOKEN_REGEX => '<span class="tok">{:$1$2}</span>'
        ];

        $summary = preg_replace(array_keys($formatting), array_values($formatting), $this->getSummary());

        return $summary;
    }


    /**
     * @param mixed $obj
     * @return string
     */
    public function getObjectOutput($obj)
    {
        if (is_object($obj) && !$obj instanceof \Traversable) {
            if (is_callable([ $obj, '__toString' ])) {
                // just use toString if available
                $obj = "$obj";
            } elseif (is_callable([ $obj, 'getId' ])) {
                // not available? ok, try for Name and Id

                $id   = $obj->getId();
                $name = is_callable([ $obj, 'getName' ]) ? $obj->getName() : get_class($obj);

                $obj = "$name ($id)";
            } else {
                $obj = get_class($obj);
            }

            // give a visual indication that this is an object
            return "<cite>$obj</cite>";

        } elseif (is_string($obj)) {
            return "'$obj'";
        }

        return $obj;
    }
} 