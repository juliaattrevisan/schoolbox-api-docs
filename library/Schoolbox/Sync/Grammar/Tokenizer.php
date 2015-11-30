<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Grammar;

use Alaress\ObjectReader;
use Alaress\Rules\Token\Filter;
use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer as Super;
use Schoolbox\External;
use Schoolbox\User;

class Tokenizer extends Super {

    /** @var string */
    protected $prefix = '';


    /**
     * @param $prefix
     * @param $map
     */
    public function __construct($prefix, $map)
    {
        $this->prefix = $prefix;

        foreach ($map as $key => $label) {
            $this->addTokenMapping($key, $label);
        }

        $this->addFilter(new Filter('lc',    'Converts to lowercase', function ($input) { return strtolower($input); }));
        $this->addFilter(new Filter('uc',    'Converts to uppercase', function ($input) { return strtoupper($input); }));
        $this->addFilter(new Filter('first', 'Returns only the first character', function ($input) { return strlen($input) ? $input[0] : ''; }));
        $this->addFilter(new Filter('alpha', 'Strips all non-alpha characters', function ($input) { return preg_replace('`[^a-z]`i', '', $input); }));

    }


    /**
     * @param $key
     * @param $label
     */
    protected function addTokenMapping($key, $label)
    {
        $context = strtolower($this->prefix);

        $this->addToken(new Token($key, "The $context's $label", function ($entity, $settings) use ($key) {
            $reader = new ObjectReader();
            return $reader->read($entity, $settings[0]);
        }));
    }


    /**
     * Override default functionality and pass the dynamic token through as a setting
     *
     * @param string $token
     * @param mixed  $dataset
     * @param array  $settings
     * @return array
     */
    protected function applyToken($token, $dataset, $settings)
    {
        return parent::applyToken($token, $dataset, [ $token ]);
    }

}
