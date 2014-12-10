<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules;


abstract class Tokenizer {

    /**
     * @param array $settings
     * @param array $ruleset
     * @param mixed $input
     * @return array
     */
    public function populateSettings(array $settings, array $ruleset, $input)
    {
        foreach ($ruleset as $field => $fieldValue) {
            $settings[$field] = $this->parseTokens($settings, $field, $fieldValue, $input);
        }

        return $settings;
    }


    /**
     * @param array  $settings
     * @param string $field
     * @param string $fieldValue
     * @param mixed  $input
     * @return mixed
     */
    protected function parseTokens($settings, $field, $fieldValue, $input)
    {
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $subField => $subFieldValue) {
                $settings[$field][$subField] = $this->parseTokens($settings, $subField, $subFieldValue, $input);
            }

        } else {
            $settings[$field] = $fieldValue;
            if (preg_match_all('`{:([a-z_][a-z0-9_]*)}`i', $fieldValue, $matches)) {
                foreach ($matches[1] as $match) {
                    // iterative replacement of tokens
                    $settings[$field] = str_replace("{:$match}", $this->renderToken($match, $input, $settings), $settings[$field]);
                }
            }
        }

        return $settings[$field];
    }


    /**
     * @param string $token
     * @param mixed  $input
     * @param array  $settings
     * @return string
     */
    protected abstract function renderToken($token, $input, $settings);

}