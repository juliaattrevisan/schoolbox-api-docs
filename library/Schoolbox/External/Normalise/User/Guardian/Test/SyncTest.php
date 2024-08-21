<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Guardian\Test;

use ArrayIterator;
use Iterator;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Normalise\User\Guardian as GuardianObject;
use Schoolbox\Sync\Iterator\Guardian as GuardianIterator;

class SyncTest extends TestCase
{
    /**
     * @param Iterator $iterator
     * @param int[]    $ids
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMultiRow')]
    public function testForEach(Iterator $iterator, array $ids)
    {
        /** @var GuardianObject $item */
        foreach ($iterator as $key => $item) {
            $this->assertInstanceOf(GuardianObject::class, $item);
            $this->assertEquals($item->getId(), $ids[$key]);
        }
    }


    public static function providerMultiRow()
    {
        $keys = [
            'student_contact_id',
            'student_contact_title',
            'student_contact_given_name',
            'student_contact_preferred',
            'student_contact_surname',
            'student_gender',
            'student_contact_default_email',
            'student_contact_home_email',
            'student_contact_work_email',
            'student_contact_network_login',
            'student_id',
            'student_preferred',
            'student_surname',
            'student_gender',
            'student_year_level',
            'student_form_description',
            'student_campus',
            'student_house',
            'custom_field1',
            'custom_field2',
            'custom_field3',
            'custom_field4',
            'custom_field5',
        ];

        $values = [
            [
                '1',
                'Mr',
                'John',
                'John',
                'Smith',
                'Male',
                'john@gmail.com',
                'john1@gmail.com',
                'john@gmail.com',
                'john_smith',
                '101',
                'Anna',
                'Smith',
                'Female',
                '9',
                'Black',
                'JUN',
                'Fox',
                'Custom User Data 1',
                'Custom User Data 2',
                'Custom User Data 3',
                'Custom User Data 4',
                'Custom User Data 5',
            ],
            [
                '1',
                'Mr',
                'John',
                'John',
                'Smith',
                'Male',
                'john@gmail.com',
                'john2@gmail.com',
                'john@gmail.com',
                'john_smith',
                '102',
                'Brad',
                'Smith',
                'Male',
                '10',
                'Brown',
                'JUN',
                'Fox',
                'Custom User Data 1',
                'Custom User Data 2',
                'Custom User Data 3',
                'Custom User Data 4',
                'Custom User Data 5',
            ],
            [
                '1',
                'Mr',
                'John',
                'John',
                'Smith',
                'Male',
                'john@gmail.com',
                'john3@gmail.com',
                'john@gmail.com',
                'john_smith',
                '103',
                'Chad',
                'Smith',
                'Male',
                '11',
                'Brown',
                'SEN',
                'Fox',
                'Custom User Data 1',
                'Custom User Data 2',
                'Custom User Data 3',
                'Custom User Data 4',
                'Custom User Data 5',
            ],
            [
                '1',
                'Mr',
                'John',
                'John',
                'Smith',
                'Male',
                'john@gmail.com',
                'john4@gmail.com',
                'john@gmail.com',
                'john_smith',
                '104',
                'Doug',
                'Smith',
                'Male',
                '12',
                'Black',
                'SEN',
                'Fox',
                'Custom User Data 1',
                'Custom User Data 2',
                'Custom User Data 3',
                'Custom User Data 4',
                'Custom User Data 5',
            ],
            [
                '2',
                'Mrs',
                'Wendy',
                'Bubbles',
                'Pierce',
                'Female',
                'wendy@gmail.com',
                null,
                'wendy2@hotmail.com',
                'wendy_pierce',
                '105',
                'Wendy Junior',
                'Pierce',
                'Female',
                '8',
                '',
                'JUN',
                'Beaver',
                'Custom User Data 1',
                'Custom User Data 2',
                'Custom User Data 3',
                'Custom User Data 4',
                'Custom User Data 5',
            ],
        ];

        $data = [];

        foreach ($values as $row) {
            $data[] = array_combine($keys, $row);
        }

        return [
            [new GuardianIterator(new ArrayIterator($data)), [1, 2],],
        ];
    }
}
