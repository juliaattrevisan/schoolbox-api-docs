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
            'id'                     => ucfirst($roleType) . ' ID',

            'account.login'          => 'Account Username',
            'account.active'         => 'Account Activation Status',

            'title'                  => 'Title',
            'preferredName'          => 'Preferred Name',
            'givenName'              => 'Given Name',
            'lastname'               => 'Last Name',

            'publiclyVisible'        => 'Visibility',
            'gender'                 => 'Gender',
            'email[].address'        => 'Default Email Address(es)',
            'workEmail[].address'    => 'Work Email Address(es)',
            'homeEmail[].address'    => 'Home Email Address(es)',

            'phoneHome.number'       => 'Home Phone Number',
            'phoneHome.silent'       => 'Home Phone Silent Status',
            'phoneMobile.number'     => 'Mobile Phone Number',
            'phoneMobile.silent'     => 'Mobile Phone Silent Status',

            'campus[].code'          => 'Campus(es) Code(s)',
        ];

        switch($roleType) {
            case Role::STAFF:
                $augment = [
                    'year.level'                => 'Year Level',
                    'house.code'                => 'House Code',
                    'phoneWork.number'          => 'Work Phone Number',
                    'phoneWork.silent'          => 'Work Phone Silent Status',
                    'meta.department'           => 'Department',
                    'meta.position'             => 'Position(s)',
                    'meta.position.description' => 'Position Description(s)',
                    'meta.positionName'         => 'Position Names',
                    'meta.category.code'        => 'Category Code',
                    'meta.category.type'        => 'Category Type',
                ];
                break;
            case Role::STUDENT:
                $augment = [
                    'year.level' => 'Year Level',
                    'form.level' => 'Form Level',
                    'house.code' => 'House Code',
                    'boardingHouse.code' => 'Boarding House Code',
                ];
                break;
            case Role::PARENT:
                $augment = [
                    'phoneWork.number'         => 'Work Phone Number',
                    'phoneWork.silent'         => 'Work Phone Silent Status',
                    'type'                     => 'Contact Type',
                    'role'                     => 'Guardian Role',
                    'students[].campus.code'   => 'Student Campus Code',
                    'students[].year.level'    => 'Student Year',
                    'students[].house.code'    => 'Student House Code',
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
