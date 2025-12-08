<?php

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

/**
 * This is just a statically-accessible lookup instance like I'd normally have written.
 *
 * Not really sure that's great for us just yet, but let's see what happens. Static referencing is generally
 * not great for our app as it reduces polymorphism and replacement, but I suppose this area isn't supposed to be
 * easily replaceable.
 */
enum Status: string
{
    /**
     * Consts correlating to the status enum used by frontend world
     * Not to be confused with the progress tick classes, despite them currently being the same strings
     * @see /library/Schoolbox/Assessment/Types/dialogAgent.ts
     */
    case INCOMPLETE = 'incomplete';
    case IN_PROGRESS = 'in-progress';
    case REQUIRES_ASSISTANCE = 'requires-assistance';
    case SUBMITTED = 'submitted';


    case INCOMPLETE_HR = 'Incomplete';
    case IN_PROGRESS_HR = 'In Progress';
    case REQUIRES_ASSISTANCE_HR = 'Requires Assistance';
    case SUBMITTED_HR = 'Submitted';


    /**
     * Convert a programmatically-meaningful state into its human-readable equivalent and vice versa
     *
     * @return string
     */
    public function translate(): string
    {
        // This is a valid use of $this:
        // see https://www.php.net/manual/en/language.enumerations.methods.php
        // phpcs:ignore
        return match ($this) {
            Status::INCOMPLETE => Status::INCOMPLETE_HR->value,
            Status::IN_PROGRESS => Status::IN_PROGRESS_HR->value,
            Status::REQUIRES_ASSISTANCE => Status::REQUIRES_ASSISTANCE_HR->value,
            Status::SUBMITTED => Status::SUBMITTED_HR->value,
            Status::INCOMPLETE_HR => Status::INCOMPLETE->value,
            Status::IN_PROGRESS_HR => Status::IN_PROGRESS->value,
            Status::REQUIRES_ASSISTANCE_HR => Status::REQUIRES_ASSISTANCE->value,
            Status::SUBMITTED_HR => Status::SUBMITTED->value,
        };
    }
}
