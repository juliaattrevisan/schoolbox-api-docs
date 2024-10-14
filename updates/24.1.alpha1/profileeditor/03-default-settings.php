<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

/* Most fields are available for All Campuses or No Campuses.
 * We might as well collect a list of campus ids to start with.
 */
$db = $this->getDi()->get('databaseInternal');

$statement = $db->prepare('SELECT id FROM luCampus');
$statement->execute();

$campusIds = [];

while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
    $campusIds[] = $row['id'];
}

/* TODO Find the field ids w/o mappers
 */

/* A list of (roletype, field name, visibility) tuples
 * and (roletype, field name, availability)
 */
$roletypes = [
    'student' => [
        'field name 1' => [
            'availability' => $campusIds,
            'visibility' => ['self', 'staff', 'student', 'parent' ],
        ],
        'field name 2' => [
            'availability' => [],
            'visibility' => ['self', 'student', 'parent'],
        ],
        'field name 3' => [
            'availability' => [],
            'visibility' => ['self', 'staff'],
        ],
        'field name ..n' => [
            'availability' => $campusIds,
            'visibility' => ['self', 'staff', 'student', 'parent' ],
        ],
    ],
    'staff' => [
        'field name 1' => [
            'availability' => $campusIds,
            'visibility' => ['self', 'staff', 'student', 'parent' ],
        ],
        'field name 2' => [
            'availability' => [],
            'visibility' => ['self', 'student', 'parent'],
        ],
        'field name 3' => [
            'availability' => [],
            'visibility' => ['self', 'staff'],
        ],
        'field name ..n' => [
            'availability' => $campusIds,
            'visibility' => ['self', 'staff', 'student', 'parent' ],
        ],
    ],
    'parent' => [
        'field name 1' => [
            'availability' => $campusIds,
            'visibility' => ['self', 'staff', 'student', 'parent' ],
        ],
        'field name 2' => [
            'availability' => [],
            'visibility' => ['self', 'student', 'parent'],
        ],
        'field name 3' => [
            'availability' => [],
            'visibility' => ['self', 'staff'],
        ],
        'field name ..n' => [
            'availability' => $campusIds,
            'visibility' => ['self', 'staff', 'student', 'parent' ],
        ],
    ]
];

/* For each field, insert their own special blend of Availability and Visibility
 */
foreach ($roletypes as $roletype => $settings) {
    foreach ($settings as $setting) {
        /* We'll need an outer loop to insert new Availability rules for each campus
         * for the roletypes listed in SA-18688
         */
        foreach ($setting['availability'] as $campusId) {
            /* $query = INSERT INTO profile_field_availability_campus
             */
        }

        /* We'll need another outer loop to insert new Visibility rules for each field
         */
        foreach ($setting['visibility'] as $roletype) {
            /* $query = INSERT INTO profile_field_visibility_roletype
             */
        }
    }
}
