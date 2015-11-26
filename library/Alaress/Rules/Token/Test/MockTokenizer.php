<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Token\Test;


use Alaress\ObjectReader;
use Alaress\Rules\Token\Filter;
use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer;

class MockTokenizer extends Tokenizer
{

    /**
     * @var array $tokens
     */
    function __construct($tokens)
    {
        foreach ($tokens as $key => $label) {
            $this->addToken(new Token($key, $label, function ($entity, $settings) use ($key) {
                $reader = new ObjectReader();
                return $reader->read($entity, $key);
            }));
        }

        $this->addFilter(new Filter('first', 'Return the first character', function ($value) {
            return strlen($value) ? $value[0] : '';
        }));
    }

}