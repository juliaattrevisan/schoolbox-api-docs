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
     * TODO this could probably be better...
     *
     * @param $prefix
     */
    public function __construct($prefix)
    {
        $this->prefix = $prefix;

        $map = [
            'id'                     => 'identifier',
            'title'                  => 'title',
            'preferredName'          => 'preferred name',
            'givenName'              => 'first given name',
            'lastname'               => 'last name',

            'publiclyVisible'        => 'visibility',
            'dob'                    => 'date of birth',
            'gender'                 => 'gender',
            'email.address'          => 'email address(es)',

            'year.level'             => 'year level',

            'account.active'         => 'account activation status',
            'account.login'          => 'account login username',

            'phoneHome.number'       => 'home phone number',
            'phoneHome.silent'       => 'home phone silent status',
            'phoneMobile.number'     => 'mobile phone number',
            'phoneMobile.silent'     => 'mobile phone silent status',

            'campus.code'            => 'campus(es) code(s)',
            'campus.name'            => 'campus(es) name(s)',

            'house.code'             => 'house code',
            'house.name'             => 'house name',
        ];

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
            return $reader->read($entity, $key);
        }));
    }

}