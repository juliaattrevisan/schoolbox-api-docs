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
 * * "[preferredname]": the student's preferred name, falling back to first name, if empty
 * * "[they]": the student's subjective pronoun.
 * * "[They]": the student's subjective pronoun, in first Char capitalised.
 * * "[them]": the student's objective pronoun.
 * * "[Them]": the student's objective pronoun, in first Char capitalised.
 * * "[theirs]": the student's possessive pronoun.
 * * "[Theirs]": the student's possessive pronoun, in first Char capitalised.
 * * "[their]": the student's possessive adjective.
 * * "[Their]": the student's possessive adjective, in first Char capitalised.
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
                '[firstname]'               => $student->getFirstname(),
                '[preferredname]'           => $student->getPreferredName() ?? $student->getFirstname(),
                '[surname]'                 => $student->getLastname(),
                '[fullname]'                => $student->getFullName(),
                '[year]'                    => $student->getYear() ? $student->getYear()->getName() : '',
                '[house]'                   => $student->getSchoolHouse() ? $student->getSchoolHouse()->getHouse() : '',
                '[they]'                    => $student->getPronoun()?->getSubjective() ?: User\Pronoun\Model::SUBJECTIVE_DEFAULT,
                '[They]'                    => ucfirst($student->getPronoun()?->getSubjective() ?: User\Pronoun\Model::SUBJECTIVE_DEFAULT),
                '[them]'                    => $student->getPronoun()?->getObjective() ?: User\Pronoun\Model::OBJECTIVE_DEFAULT,
                '[Them]'                    => ucfirst($student->getPronoun()?->getObjective() ?: User\Pronoun\Model::OBJECTIVE_DEFAULT),
                '[theirs]'                  => $student->getPronoun()?->getPossessive() ?: User\Pronoun\Model::POSSESSIVE_DEFAULT,
                '[Theirs]'                  => ucfirst($student->getPronoun()?->getPossessive() ?: User\Pronoun\Model::POSSESSIVE_DEFAULT),
            ]
        );
    }
}
