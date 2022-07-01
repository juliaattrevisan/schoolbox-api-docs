<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar;

use Alaress\ObjectReader;
use Alaress\Rules\Token\Filter;
use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer as Super;

/**
 * Class Tokenizer
 *
 * @package Schoolbox\Sync\Grammar
 */
class Tokenizer extends Super
{
    /**
     * @var string
     */
    protected $prefix = '';


    /**
     * @param string $prefix
     * @param array $map
     */
    public function __construct($prefix, $map)
    {
        $this->prefix = $prefix;

        foreach ($map as $key => $label) {
            $this->addTokenMapping($key, $label);
        }

        $this->addFilter(new Filter('lc', 'Converts to lowercase', function ($input) {
            return strtolower($input);
        }));
        $this->addFilter(new Filter('uc', 'Converts to uppercase', function ($input) {
            return strtoupper($input);
        }));
        $this->addFilter(new Filter('first', 'Returns only the first character', function ($input) {
            return strlen($input) ? $input[0] : '';
        }));
        $this->addFilter(new Filter('cutfirst', 'Returns everything after the first character', function ($input) {
            return substr($input, 1);
        }));
        $this->addFilter(new Filter('alpha', 'Strips all non-alpha characters', function ($input) {
            return preg_replace('`[^a-z]`i', '', $input);
        }));
        $this->addFilter(new Filter('num', 'Strips all non-numeric characters', function ($input) {
            return preg_replace('`[^0-9]`i', '', $input);
        }));
        $this->addFilter(new Filter('preat', 'All characters before the first @ symbol', function ($input) {
            $pos = strpos($input, '@');
            return ($pos !== false) ? substr($input, 0, $pos) : $input;
        }));
        $this->addFilter(new Filter('postslash', 'All characters after the last \\ symbol', function ($input) {
            $pos = strrpos($input, '\\');
            return ($pos !== false) ? substr($input, $pos + 1) : $input;
        }));
    }


    /**
     * @param string $key
     * @param string $label
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
