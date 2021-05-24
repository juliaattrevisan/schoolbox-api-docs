<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Rules;

use Alaress\Rules\Token\Filter;
use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer;

abstract class TokenizedRuleset extends Ruleset
{

    /** @var Tokenizer */
    protected $tokenizer;


    /**
     * @param Tokenizer $tokenizer
     */
    public function __construct(Tokenizer $tokenizer)
    {
        parent::__construct();
        $this->tokenizer = $tokenizer;
    }


    /**
     * @return Token[]
     */
    public function getTokens()
    {
        return $this->tokenizer->getTokens();
    }


    /**
     * @return Filter[]
     */
    public function getFilters()
    {
        return $this->tokenizer->getFilters();
    }
}
