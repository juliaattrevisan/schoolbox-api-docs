<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\WebDav\Rules;

use Alaress\Rules\Tokenizer as Super;
use Schoolbox\User;

class Tokenizer extends Super {

    /**
     * @param string $token
     * @param User   $user
     * @param array  $settings
     * @return string
     */
    protected function renderToken($token, $user, $settings)
    {
        switch ($token) {
            case 'username':    return $user->getUsername();
            case 'password':    return $user->getPassword();
            case 'lc_username': return strtolower($user->getUsername());
            case 'pc_username': return ucwords($user->getUsername());
            case 'YYYY':        return $user->getYear();
            case 'YY':          return str_pad($user->getYear(), 2, '0', STR_PAD_LEFT);
            case 'yr12_YY':     return 12 - $user->getYear() + date('y');
            case 'yr12_YYYY':   return 12 - $user->getYear() + date('Y');
            case 'yr7_YYYY':    return date('Y') - ($user->getYear() - 7);
            case 'yr7_YY':      return date('y') - ($user->getYear() - 7);
            case 'base_uri':    return $settings['baseUri'];
            default:
                return '';
        }
    }

}