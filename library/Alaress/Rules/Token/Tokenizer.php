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
use DomainException;
use OutOfBoundsException;

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
                    \|[a-z]+            # filters are prepended with a pipe, then alpha only
                )*
            )?                          # /$2
        }                               # token end
    `ix';

    /** @var Token[] */
    protected $tokens = [];

    /** @var Filter[] */
    protected $filters = [];

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
     * Process a token string with the given dataset
     *
     * @param mixed            $dataSet         This is the dataset from which values will be taken and jammed into the tokenized string. Typically an array- or object-based hierarchy.
     * @param string|Construct $tokenString     A string or Construct that will determine how to render the output. Typically looks like "{:this}".
     * @return array|string                     The rendered string (or array thereof if the dataset returned an array for a particular token).
     */
    public function parseToken($dataSet, $tokenString)
    {
        return $this->parseTokens([], '_', $tokenString, $dataSet);
    }


    /**
     * @param array                  $settings      (If in doubt, leave this empty)
     * @param string                 $field         Only means anything if you passed anything through $settings and $fieldValue is an array.
     * @param string|array|Construct $fieldValue    A string or Construct (or array structure thereof) that will determine how to render the output. Typically looks "something{:like|this}".
     * @param mixed                  $dataset       This is the dataset from which values will be taken and jammed into the tokenized string. Typically an array- or object-based hierarchy.
     * @return string|array                         The rendered string (or array thereof if the dataset returned an array for a particular token).
     */
    protected function parseTokens($settings, $field, $fieldValue, $dataset)
    {
        // if field value is an array, dig into it and recursively parse
        if (is_array($fieldValue)) {
            // recurse
            foreach ($fieldValue as $subField => $subFieldValue) {
                $settings[$field][$subField] = $this->parseTokens($settings, $subField, $subFieldValue, $dataset);
            }
            return $settings[$field];
        }

        // most of the time $fieldValue will just be a string, but if it's a Construct, split that into its own variable for safe keeping (we'll use it later)
        if ($fieldValue instanceof Construct) {
            // rescue the construct before we lose it
            $construct  = $fieldValue;
            $fieldValue = $fieldValue->getField();
        }

        // from here we can't operate on anything other than a string
        if (!is_string($fieldValue)) {
            // TODO consider an exception here, unsure what will be most appropriate
            // for now, throw back whatever was passed as-is
            return $fieldValue;
        }

        // search the input string ($fieldValue) for tokens
        if (preg_match_all(self::TOKEN_REGEX, $fieldValue, $tokenMatches)) {

            $tokenCount = count($tokenMatches[0]);

            $tokenMap = [];

            // iterate the tokens found and apply each of them to the input string ($fieldValue)
            for ($i = 0; $i < $tokenCount; ++$i) {

                // replace array[notation] with dot.notation
                $tokenKey     = preg_replace('`\[(\d+)\]`', '.$1', $tokenMatches[1][$i]);
                $substitution = $tokenMatches[0][$i];
                $filterString = $tokenMatches[2][$i];

                // for the currently iterated token key, get the corresponding value(s) from the given $input via the set of Tokens registered against this Tokenizer
                $replacements = $this->renderToken($tokenKey, $dataset, $settings);

                // apply any filters by mutating $replacement
                if ($filterString) {
                    $filters = array_filter(explode('|', $filterString));

                    // apply all filters to all replacements
                    foreach ($replacements as $j => $replacement) {
                        foreach ($filters as $filterName) {
                            $replacements[$j] = $this->applyFilter($filterName, $replacement);
                        }
                    }
                }

                $tokenMap[$substitution] = $replacements;
            }

            // this is where it gets fun. we're attempting a cartesian product of $tokenMap[*][1]
            $cartesian = $this->cartesian($tokenMap);

            // iterate that result and apply the replacements
            foreach ($cartesian as $permutation) {
                $settings[$field][] = str_replace(
                    array_keys($permutation),
                    array_values($permutation),
                    $fieldValue
                );
            }

        } else {
            // no tokens - just set as-is
            $settings[$field] = [ $fieldValue ];
        }

        // apply the construct we rescued above
        if (isset($construct)) {
            $settings[$field] = (array)$construct->apply($settings[$field]);
        }

        // if one value, return as string, otherwise return the array of strings
        return count($settings[$field]) == 1 ? $settings[$field][0] : $settings[$field];
    }


    /**
     * Return a cartesian product of the given array
     *
     * @see http://stackoverflow.com/a/15973172
     * @param array $input
     * @return array
     */
    protected function cartesian(array $input) {
        // filter out empty values
        $input = array_filter($input);

        $result = [[]];

        foreach ($input as $key => $values) {
            $append = [];

            foreach($result as $product) {
                foreach($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        return $result;
    }

    /**
     * @param string $token
     * @param mixed  $dataset
     * @param array  $settings
     * @throws DomainException
     * @return array
     */
    protected function renderToken($token, $dataset, $settings)
    {
        try {
            // direct token exists?
            $token = strtolower($token);
            return $this->applyToken($token, $dataset, $settings);
        } catch (OutOfBoundsException $e) {
            try {
                // does this token exist without numbers in it?
                $token = preg_replace('`\.\d+`', '', $token);
                return $this->applyToken($token, $dataset, $settings);
            } catch (OutOfBoundsException $e) {
                // no matches: go home hungry
                return [''];
            }
        }
    }


    /**
     * @param string $token
     * @param mixed  $dataset
     * @param array  $settings
     * @return array
     */
    protected function applyToken($token, $dataset, $settings)
    {
        if (empty($this->tokens[$token])) {
            throw new OutOfBoundsException("Token '$token' is not registered");
        }

        $reader = new ObjectReader();

        $values = (array)$this->tokens[$token]->apply($dataset, $settings);

        foreach ($values as $i => $value) {
            $values[$i] = $reader->coerceToString($value);
        }

        return $values;
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