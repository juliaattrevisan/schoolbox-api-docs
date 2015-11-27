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
    public function __construct($tokens)
    {
        foreach ($tokens as $key => $label) {

            $this->addToken(new Token($key, $label, function ($entity, $settings) {
                $reader = new ObjectReader();
                return $reader->read($entity, $settings[0]);
            }));
        }

        $this->addFilter(new Filter('first', 'Return the first character', function ($value) {
            return strlen($value) ? $value[0] : '';
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
