<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\WebDav\Rules;

use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer as Super;
use Schoolbox\User;

class Tokenizer extends Super {

    /**
     * TODO this could probably be better...
     */
    public function __construct()
    {
        parent::__construct();

        $this->addToken(new Token('username', "The user's username", function ($user, $settings) {
            /** @var User $user */
            return $user->getUsername();
        }));

        $this->addToken(new Token('username|lc', "The user's username cast to lowercase", function ($user, $settings) {
            /** @var User $user */
            return strtolower($user->getUsername());
        }));

        $this->addToken(new Token('username|pc', "The user's username cast to ProperCase", function ($user, $settings) {
            /** @var User $user */
            return ucwords($user->getUsername());
        }));

        $this->addToken(new Token('password', "The user's password", function ($user, $settings) {
            /** @var User $user */
            return $user->getPassword();
        }));

        $this->addToken(new Token('year', "The user's year level", function ($user, $settings) {
            /** @var User $user */
            return $user->getYear();
        }));

        $this->addToken(new Token('year|YY', "The user's year level cast to 2 digits", function ($user, $settings) {
            /** @var User $user */
            return str_pad($user->getYear(), 2, '0', STR_PAD_LEFT);
        }));

        $this->addToken(new Token('yr12|YY', "The user's graduating year, cast to 2 digits", function ($user, $settings) {
            /** @var User $user */
            return 12 - $user->getYear() + date('y');
        }));

        $this->addToken(new Token('yr12|YYYY', "The user's graduating year, cast to 4 digits", function ($user, $settings) {
            /** @var User $user */
            return 12 - $user->getYear() + date('Y');
        }));

        $this->addToken(new Token('yr7|YY', "The user's starting year, cast to 2 digits", function ($user, $settings) {
            /** @var User $user */
            return date('y') - ($user->getYear() - 7);
        }));


        $this->addToken(new Token('yr7|YY', "The user's starting year, cast to 4 digits", function ($user, $settings) {
            /** @var User $user */
            return date('Y') - ($user->getYear() - 7);
        }));

        $this->addToken(new Token('uri', "The base URI defined for the user", function ($user, $settings) {
            /** @var User $user */
            return $settings['baseUri'];
        }));

    }

}