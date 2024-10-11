<?php
/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

$campusMapper = \Schoolbox\CampusMapper::getInstance();
$fieldMapper = \Schoolbox\Admin\Profile\Editor\Field\Mapper::getInstance();

/* Most fields are available for All Campuses or No Campuses.
 * We might as well collect a list of campus ids to start with.
 */
$campusIds = $campusMapper->findAll()->indexBy('id');

/* All the fields for which we must provided default settings
 */
$fields = $fieldMapper->findAll();

/* A list of (roletype, field name, visibility) tuples
 */
$visibilities = [
    'field name 1' => ['self', 'staff', 'student', 'parent' ],
    'field name 2' => ['self', 'staff', 'student', 'parent' ],
    'field name 3' => ['self', 'staff', 'student', 'parent' ],
    'field name ..n' => ['self', 'staff', 'student', 'parent' ],
];

/* For each field, insert their own special blend of Availability and Visibility
 */
foreach ($fields as $field) {
    /* We'll need an outer loop to insert new Availability rules for each campus
     * for the roletypes listed in SA-18688
     */
    foreach ($campusIds as $campusId) {
        /* $query = INSERT INTO profile_field_availability_campus
         */
    }

    /* We'll need another outer loop to insert new Visibility rules for each field
     */
    foreach ($visibilities[$field->getName()] as $visibility) {
        /* $query = INSERT INTO profile_field_visibility_roletype
         */
    }
}