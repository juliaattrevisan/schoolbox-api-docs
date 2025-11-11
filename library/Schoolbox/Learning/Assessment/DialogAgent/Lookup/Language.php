<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Lookup;

class Language
{
    /**
     * Preselect this one by default if no languages were chosen yet (ie. crafting a new dialog agent)
     */
    public const string DEFAULT = 'English (AU)';


    /**
     * Supplied by Andrew
     */
    public const array AVAILABLE = [
        'Arabic',
        'Chinese (Mandarin) Simplified',
        'Chinese (Mandarin) Traditional',
        'Dutch',
        'English (AU)',
        'English (UK)',
        'English (US)',
        'French',
        'German',
        'Greek',
        'Hebrew',
        'Hindi',
        'Indonesian',
        'Italian',
        'Japanese',
        'Korean',
        'Malay',
        'Maori',
        'Persian',
        'Russian',
        'Spanish',
        'Thai',
        'Turkish',
        'Vietnamese',
    ];
}
