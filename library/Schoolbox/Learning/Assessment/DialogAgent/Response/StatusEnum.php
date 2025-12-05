<?php

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

/**
 * This is just a statically-accessible lookup instance like I'd normally have written.
 *
 * Not really sure that's great for us just yet, but let's see what happens. Static referencing is generally
 * not great for our app as it reduces polymorphism and replacement, but I suppose this area isn't supposed to be
 * easily replaceable.
 */
enum StatusEnum: string
{
    case STATUS_INCOMPLETE = 'Incomplete';
    case STATUS_IN_PROGRESS = 'In Progress';
    case STATUS_REQUIRES_ASSISTANCE = 'Requires Assistance';
    case STATUS_SUBMITTED = 'Submitted';
}
