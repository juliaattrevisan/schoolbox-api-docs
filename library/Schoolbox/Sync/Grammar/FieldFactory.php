<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Grammar;


class FieldFactory
{

    /**
     * @param string $roleType
     * @return array
     */
    public function buildMap($roleType)
    {
        /* This might change based on Role Type
         * Construct it here and pass it all the way down to Tokenizer
         */
        $map = [
            'id'                     => ucfirst($roleType) . ' ID',

            'account.login'          => 'Account Username',
            'account.active'         => 'Account Activation Status',

            'title'                  => 'Title',
            'preferredName'          => 'Preferred Name',
            'givenName'              => 'Given Name',
            'lastname'               => 'Last Name',
            'year.level'             => 'Year Level',

            'publiclyVisible'        => 'Visibility',
            'dob'                    => 'Date of Birth',
            'gender'                 => 'Gender',
            'email[].address'        => 'Email Address(es)',

            'phoneHome.number'       => 'Home Phone Number',
            'phoneHome.silent'       => 'Home Phone Silent Status',
            'phoneMobile.number'     => 'Mobile Phone Number',
            'phoneMobile.silent'     => 'Mobile Phone Silent Status',

            'campus[].code'          => 'Campus(es) Code(s)',
            'campus[].name'          => 'Campus(es) Name(s)',

            'house.code'             => 'House Code',
            'house.name'             => 'House Name',

            'residentialHouse'       => 'Boarding House Code',
        ];

        return $map;
    }

}
