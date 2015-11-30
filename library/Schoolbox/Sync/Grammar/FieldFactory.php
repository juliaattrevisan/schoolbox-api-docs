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
            'title'                  => 'Title',
            'preferredName'          => 'Preferred Name',
            'givenName'              => 'Given Name',
            'lastname'               => 'Last Name',

            'publiclyVisible'        => 'Visibility',
            'dob'                    => 'Date of Birth',
            'gender'                 => 'Gender',
            'email[].address'        => 'Email Address(es)',

            'year.level'             => 'Year Level',

            'account.active'         => 'Account Activation Status',
            'account.login'          => 'Account Username',

            'phoneHome.number'       => 'Home phone number',
            'phoneHome.silent'       => 'Home phone silent status',
            'phoneMobile.number'     => 'Mobile phone number',
            'phoneMobile.silent'     => 'Mobile phone silent status',

            'campus[].code'          => 'Campus(es) Code(s)',
            'campus[].name'          => 'Campus(es) Name(s)',

            'house.code'             => 'House Code',
            'house.name'             => 'House Name',

            'residentialHouse'       => 'Boarding House Code',
        ];

        return $map;
    }

}
