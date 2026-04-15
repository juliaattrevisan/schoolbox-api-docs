<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
            'id' => ucfirst($roleType) . ' ID',

            'account.login'  => 'Account Username',
            'account.active' => 'Account Activation Status',

            'title'         => 'Title',
            'preferredName' => 'Preferred Name',
            'givenName'     => 'Given Name',
            'lastname'      => 'Last Name',

            'email[].address'     => 'Default Email Address(es)',
            'workEmail[].address' => 'Work Email Address(es)',
            'homeEmail[].address' => 'Home Email Address(es)',

            'campus[].code' => 'Campus(es) Code(s)',

            'customField1' => 'Custom Field 1',
            'customField2' => 'Custom Field 2',
            'customField3' => 'Custom Field 3',
            'customField4' => 'Custom Field 4',
            'customField5' => 'Custom Field 5',
        ];

        switch ($roleType) {
            case Role::STAFF:
                $augment = [
                    'year.level'                => 'Year Level',
                    'house.code'                => 'House Code',
                    'meta.department'           => 'Department',
                    'meta.position.code'        => 'Position Code(s)',
                    'meta.position.description' => 'Position Description(s)',
                    'meta.position'             => 'Position Title(s) (deprecated)',
                    'meta.category.code'        => 'Category Code',
                    'meta.category.type'        => 'Category Type',
                ];
                break;
            case Role::STUDENT:
                $augment = [
                    'year.level'         => 'Year Level',
                    'form.description'   => 'Form Name',
                    'house.code'         => 'House Code',
                    'boardingHouse.code' => 'Boarding House Code',
                    'boardingStatus'     => 'Boarding Status',
                    'gender'             => 'Gender',
                    'nationality[].name' => 'Nationalities',
                ];
                break;
            case Role::PARENT:
                $augment = [
                    'type'                     => 'Contact Type',
                    'students[].campus[].code' => 'Student Campus(es) Code(s)',
                    'students[].form.description' => 'Student Form Name',
                    'students[].year.level'    => 'Student Year',
                    'students[].house.code'    => 'Student House Code(s)',
                    'students[].gender'        => 'Student Gender',
                ];
                break;
            default:
                $augment = [];
                break;
        }

        return array_merge($map, $augment);
    }
}
