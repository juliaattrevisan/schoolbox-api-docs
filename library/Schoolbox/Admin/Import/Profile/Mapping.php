<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Schoolbox\Role;

/**
 * Class Mapping
 *
 * A set of consts that translates CSV headings to the schema column to
 * which they are conceptually related
 *
 * The CSV is an amalgamation of multiple mappers' columns, and conversely,
 * is disseminated into multiple mappers' columns
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Mapping
{

    /**
     * Strings that denote a csv file
     */
    public const CSV_STUDENT = Role::STUDENT;
    public const CSV_STAFF = Role::STAFF;
    public const CSV_PARENT = Role::PARENT;
    public const CSV_RELATION = 'relation';


    /**
     * Columns in a Parent CSV
     */
    public const IMPORT_COLUMNS_PARENT = [
        'External ID' => 'external_id',
        'Type' => self::CSV_PARENT,
        'Username' => 'username',
        'Title' => 'title',
        'Firstname' => 'firstname',
        'Surname' => 'lastname',
        'Email' => 'email',

        'Mobile' => 'mobile',
        'Home Phone' => 'phone_home',
        'Work Phone' => 'phone_work'
    ];


    /**
     * Columns in a Student csv
     */
    public const IMPORT_COLUMNS_STUDENT = [
        'External ID' => 'external_id',
        'Type' => self::CSV_STUDENT,
        'Username' => 'username',
        'Title' => 'title',
        'Firstname' => 'firstname',
        'Surname' => 'lastname',
        'Email' => 'email',

        'Date of Birth' => 'date_of_birth',
        'Gender' => 'gender',
        'Mobile' => 'mobile',
        'Emergency Contact Name 1' => 'emergency_contact_name_1',
        'Emergency Contact Phone 1' => 'emergency_contact_phone_1',
        'Emergency Contact Phone Alt 1' => 'emergency_contact_phone_alt_1',
        'Emergency Contact Name 2' => 'emergency_contact_name_2',
        'Emergency Contact Phone 2' => 'emergency_contact_phone_2',
        'Emergency Contact Phone Alt 2' => 'emergency_contact_phone_alt_2',
        'Emergency Contact Name 3' => 'emergency_contact_name_3',
        'Emergency Contact Phone 3' => 'emergency_contact_phone_3',
        'Emergency Contact Phone Alt 3' => 'emergency_contact_phone_alt_3',
        'Campus' => 'campus_name',
        'House' => 'house_name',
        'Head of House ID' => 'head_of_house_id',
        'Head of House Name' => 'head_of_house_name',
        'Form' => 'form_name',
        'Form Teacher ID' => 'form_teacher_id',
        'Form Teacher Name' => 'form_teacher_name',
        'Tutor Group' => 'tutor_group',
        'Tutor Teacher ID' => 'tutor_teacher_id',
        'Tutor Teacher Name' => 'tutor_teacher_name',
        'Year Level' => 'year_level',
        'Year Level Coordinator ID' => 'year_level_coordinator_id',
        'Year Level Coordinator Name' => 'year_level_coordinator_name',
        'Studies Code' => 'studies_code',
        'Medical Alert' => 'alert_medical',
        'Comment' => 'alert_comment',
        'Contact Alert' => 'alert_message',
        'Legal Alert' => 'alert_legal',
        'Enrolment Date' => 'enrolment_date',
        'Leaving Date' => 'leaving_date',
        'IB' => 'ib',
        'ILP' => 'individual_learning_plans',
        'ESL' => 'esl',
        'Special Needs' => 'special_needs',
        'Boarding Status' => 'boarding_status'
    ];


    /**
     * Columns in a Staff csv
     */
    public const IMPORT_COLUMNS_STAFF = [
        'External ID' => 'external_id',
        'Type' => self::CSV_STAFF,
        'Username' => 'username',
        'Title' => 'title',
        'Firstname' => 'firstname',
        'Surname' => 'lastname',
        'Email' => 'email',

        'Campus' => 'campus_name',
        'Work Phone' => 'phone',
        'Staff Category' => 'category',
        'Position Title' => 'position_title',
        'Department' => 'department',
        'Building' => 'building',
        'Room' => 'room',
    ];


    /**
     * Columns in a Relation csv
     */
    public const IMPORT_COLUMNS_RELATION = [
        'Student ID' => 'child_profile_id',
        'Guardian ID' => 'user_profile_id',
        'Type' => 'type',
    ];
}
