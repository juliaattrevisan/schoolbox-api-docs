<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting;

/**
 * This is just a statically-accessible lookup instance like I'd normally have written.
 */
enum Folder: string
{
    /**
     * Consts correlating to the folder options used by frontend world
     */
    case CURRENT_ONLY = 'current_only';
    case ANY_FOLDERS = 'any_folders';


    /**
     * Returns a key-value set in a convenient struct
     *
     * @return array
     */
    public static function lookup(): array
    {
        return [
            Folder::CURRENT_ONLY->value => 'Current class folder',
            Folder::ANY_FOLDERS->value => 'Any folders where students have published feedback',
        ];
    }
}
