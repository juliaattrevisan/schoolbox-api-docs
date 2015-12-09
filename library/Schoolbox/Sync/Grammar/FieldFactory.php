<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Grammar;


use Schoolbox\Role;

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

            'publiclyVisible'        => 'Visibility',
            'gender'                 => 'Gender',
            'email[].address'        => 'Email Address(es)',

            'phoneHome.number'       => 'Home Phone Number',
            'phoneHome.silent'       => 'Home Phone Silent Status',
            'phoneMobile.number'     => 'Mobile Phone Number',
            'phoneMobile.silent'     => 'Mobile Phone Silent Status',

            'campus[].code'          => 'Campus(es) Code(s)',
            'campus[].name'          => 'Campus(es) Name(s)',

            'residentialHouse'       => 'Boarding House Code',
        ];

        switch($roleType) {
            case Role::STAFF:
                $augment = [
                    'phoneWork.number'   => 'Work Phone Number',
                    'phoneWork.silent'   => 'Work Phone Silent Status',
                    'meta.department'    => 'Department',
                    'meta.position'      => 'Position',
                    'meta.category.code' => 'Category Code',
                    'meta.category.type' => 'Category Type',
                ];
                break;
            case Role::STUDENT:
                $augment = [
                    'year.level' => 'Year Level',
                    'form.level' => 'Form Level',
                    'house.code' => 'House Code',
                    'house.name' => 'House Name',
                ];
                break;
            case Role::PARENT:
                $augment = [
                    'phoneWork.number' => 'Work Phone Number',
                    'phoneWork.silent' => 'Work Phone Silent Status',
                    'type'             => 'Contact Type',
                    'role'             => 'Guardian Role',
                ];
                break;
            default:
                $augment = [];
                break;
        }

        return array_merge($map, $augment);
    }

}
