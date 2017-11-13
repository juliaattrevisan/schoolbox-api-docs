<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Normalise\User\Test\Guardian;

use ArrayIterator;
use Iterator;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Normalise\User\Guardian as GuardianObject;
use Schoolbox\Sync\Iterator\Guardian as GuardianIterator;

class SyncTest extends TestCase
{

    /**
     * @dataProvider provider_multi_row
     * @param Iterator $iterator
     * @param int[]    $ids
     */
    public function testForEach(Iterator $iterator, array $ids)
    {
        /** @var GuardianObject $item */
        foreach ($iterator as $key => $item) {
            $this->assertInstanceOf(GuardianObject::class, $item);
            $this->assertEquals($item->getId(), $ids[$key]);
        }
    }


    public function provider_multi_row()
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
            'student_campus',
            'student_house',
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
                'JUN',
                'Fox',
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
                'JUN',
                'Fox',
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
                'SEN',
                'Fox',
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
                'SEN',
                'Fox',
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
                'JUN',
                'Beaver',
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
