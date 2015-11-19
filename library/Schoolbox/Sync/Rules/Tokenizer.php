<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rules;

use Alaress\Rules\Token\Filter;
use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer as Super;
use Schoolbox\User;

class Tokenizer extends Super {

    /** @var string */
    protected $prefix = '';


    /**
     * TODO this could probably be better...
     *
     * @param $prefix
     */
    public function __construct($prefix)
    {
        $this->prefix = $prefix;

        parent::__construct();

        $this->addToken(new Token("NetworkLogin", "The user's network login",
            function ($externalStudent, $settings) {
                return $externalStudent['NetworkLogin'];
            }
        ));

        $externalKeys = [
            'ID'            => 'external ID',
            'Title'         => 'title',
            'Preferred'     => 'preferred name',
            'Surname'       => 'last name',
            'Gender'        => 'gender',
            'Email'         => 'home email address',
            'OccupEmail'    => 'work email address',
            'YearLevel'     => 'year level',
            'House'         => 'house',
            'Boarder'       => 'boarding status',
            'BoardingHouse' => 'boarding house, if a boarder',
            'Campus'        => 'default campus'
        ];

        foreach ($externalKeys as $key => $label) {

            $this->addToken(new Token("$this->prefix$key", "The user's $label",
                function ($externalStudent, $settings) use ($key) {
                    return $externalStudent["$this->prefix$key"];
                }
            ));

        }


        $this->addFilter(new Filter('lc',    'Converts to lowercase', function ($input) { return strtolower($input); }));
        $this->addFilter(new Filter('uc',    'Converts to uppercase', function ($input) { return strtoupper($input); }));
        $this->addFilter(new Filter('first', 'Returns only the first character', function ($input) { return $input[0]; }));
        $this->addFilter(new Filter('alpha', 'Strips all non-alpha characters', function ($input) { return preg_replace('`[^a-z]`i', '', $input); }));

    }

}