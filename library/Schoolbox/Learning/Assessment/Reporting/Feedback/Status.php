<?php

namespace Schoolbox\Learning\Assessment\Reporting\Feedback;

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
     * Not to be confused with the progress tick classes
     *
     * Not sure if reporting activities ever get represented with a progress tick but this is the most recent
     * precedent of such an idea:
     * @see /library/Schoolbox/Assessment/Types/dialogAgent.ts
     */
    case INCOMPLETE = 'incomplete';
    case DRAFT = 'draft';
    case PUBLISHED = 'published';

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
            Status::INCOMPLETE => 'Incomplete',
            Status::DRAFT => 'Draft',
            Status::PUBLISHED => 'Published',
        };
    }
}
