<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TokenReplacer;

use Schoolbox\User;

/**
 * Wrapper around `Simple`, designed to replace tokens for a student.
 *
 * Supports the following tokens:
 * * "[firstname]": the student's first name
 * * "[surname]":   the student's surname
 * * "[fullname]":  the student's full name
 * * "[year]":      the student's year level, or an empty string if not defined
 * * "[house]":     the student's school (not residential) house, or an empty
 *                  string if not defined
 */
class Student
{
    /**
     * The token replacer this student token replacer uses internally.
     */
    private Simple $tokenReplacer;

    /**
     * Constructor
     *
     * @param Simple $tokenReplacer
     */
    public function __construct(Simple $tokenReplacer)
    {
        $this->tokenReplacer = $tokenReplacer;
    }

    /**
     * Replaces tokens in
     *
     * @param string $body
     * @param User   $student
     * @return string
     */
    public function replace(string $body, User $student): string
    {
        return $this->tokenReplacer->replace(
            $body,
            [
                '[firstname]'     => $student->getFirstname(),
                '[preferredname]' => $student->getPreferredName(),
                '[surname]'       => $student->getLastname(),
                '[fullname]'      => $student->getFullName(),
                '[year]'          => $student->getYear() ? $student->getYear()->getLongName() : '',
                '[house]'         => $student->getSchoolHouse() ? $student->getSchoolHouse()->getHouse() : '',
            ]
        );
    }
}
