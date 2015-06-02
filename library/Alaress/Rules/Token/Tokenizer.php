<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Token;


use Alaress\Rules\Lang\Construct;

abstract class Tokenizer {

    /** @var Token[] */
    protected $tokens;

    /**
     */
    public function __construct()
    {
        $this->tokens = array();
    }


    /**
     * @return Token[]
     */
    public function getTokens()
    {
        return array_values($this->tokens);
    }

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
     * @param Token $token
     * @return self
     */
    protected function addToken($token)
    {
        // index by token
        $this->tokens[strtolower($token->getToken())] = $token;
        return $this;
    }

    /**
     * @param array  $settings
     * @param string $field
     * @param string $fieldValue
     * @param mixed  $input
     * @return mixed
     */
    public function parseTokens($settings, $field, $fieldValue, $input)
    {
        if (is_array($fieldValue)) {
            // recurse
            foreach ($fieldValue as $subField => $subFieldValue) {
                $settings[$field][$subField] = $this->parseTokens($settings, $subField, $subFieldValue, $input);
            }
            return $settings[$field];
        }

        if ($fieldValue instanceof Construct) {
            // rescue the construct before we lose it
            $construct  = $fieldValue;
            $fieldValue = $fieldValue->getField();
        }

        if (is_string($fieldValue)) {
            $settings[$field] = $fieldValue;
            if (preg_match_all('`{:([a-z_][a-z0-9_]*)}`i', $fieldValue, $matches)) {
                foreach ($matches[1] as $match) {
                    // iterative replacement of tokens
                    $settings[$field] = str_replace("{:$match}", $this->renderToken($match, $input, $settings), $settings[$field]);
                }
            }
        } else {
            // just assign the value
            $settings[$field] = $fieldValue;
        }

        // apply the construct we rescued above
        if (isset($construct)) {
            $settings[$field] = $construct->apply($settings[$field]);
        }

        return $settings[$field];
    }


    /**
     * @param string $token
     * @param mixed  $input
     * @param array  $settings
     * @return string
     */
    protected function renderToken($token, $input, $settings)
    {
        $token = strtolower($token);
        if (empty($this->tokens[$token])) {
            // TODO should this throw an OOB?
            return '';
        }
        return $this->tokens[$token]->apply($input, $settings);
    }

}