<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Token;


use Alaress\ObjectReader;
use Alaress\Rules\Lang\Construct;

abstract class Tokenizer
{
    const TOKEN_REGEX = '`
        {:                              # token start
            (                           # $1
                [a-z_]                  # must start with alpha or underscore
                [a-z_0-9\.\[\]]*        # only alphanum, period or squarebrackets allowed
            )                           # /$1
            (                           # $2
                (?:
                    \s*\|\s*[a-z]+      # filters are prepended with a pipe, then alpha only
                )*
            )?                          # /$2
        }                               # token end
    `ix';

    /** @var Token[] */
    protected $tokens;

    /** @var Filter[] */
    protected $filters;


    /**
     */
    public function __construct()
    {
        $this->tokens = array();
        $this->filters = array();
    }


    /**
     * @return Token[]
     */
    public function getTokens()
    {
        return array_values($this->tokens);
    }


    /**
     * @return Filter[]
     */
    public function getFilters()
    {
        return array_values($this->filters);
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
     * @param Filter $filter
     * @return self
     */
    protected function addFilter($filter)
    {
        $this->filters[strtolower($filter->getFilter())] = $filter;
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
            if (preg_match_all(self::TOKEN_REGEX, $fieldValue, $matches)) {

                $len = count($matches[0]);

                for ($i = 0; $i < $len; ++$i) {

                    $match  = $matches[0][$i];
                    $token  = $matches[1][$i];
                    $filter = $matches[2][$i];

                    // iterative replacement of tokens
                    $replacement = $this->renderToken($token, $input, $settings);

                    // apply any filters by mutating $replacement
                    if ($filter) {
                        $filters = array_filter(explode('|', $filter));

                        foreach ($filters as $filterName) {
                            $replacement = $this->applyFilter($filterName, $replacement);
                        }
                    }

                    $settings[$field] = str_replace($match, $replacement, $settings[$field]);
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

        $reader = new ObjectReader();

        return $reader->coerceToString($this->tokens[$token]->apply($input, $settings));
    }


    /**
     * @param string $filter
     * @param string $input
     * @return string
     */
    protected function applyFilter($filter, $input)
    {
        $filter = strtolower($filter);
        if (empty($this->filters[$filter])) {
            return $input;
        }
        return $this->filters[$filter]->apply($input);
    }

}