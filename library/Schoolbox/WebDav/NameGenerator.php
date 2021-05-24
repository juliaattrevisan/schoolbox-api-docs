<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav;

use Sabre\DAV;

/**
 * Helper class for generating unique names for a WebDAV node.
 */
class NameGenerator
{
    /**
     * Strips characters which may have special meaning in filenames.
     * Currently strips out:
     * * All non-printable chars and null (\x00 - \x1F)
     * * The following characters: < > : " / \ | ? *
     *
     * @see http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247(v=vs.85).aspx
     *
     * @param  string $name
     *
     * @return string
     */
    public function sanitiseName($name)
    {
        return preg_replace('/[<>:\\/\\\\|?*\\x00-\\x1F]/', '', $name);
    }


    /**
     * Attempts to resolve conflicts with names by appending a number to
     * the name if it's already in use.
     *
     * @throws DAV\Exception if this method couldn't create a unique name
     *                       after 1000 attempts
     *
     * @param  DAV\Collection $node The node to generate a child name for
     * @param  string         $name The name to make unique
     *
     * @return string
     */
    public function getUniqueChildName(DAV\Collection $node, $name)
    {
        if ($node->childExists($name)) {
            for ($i = 1; $i <= 1000; $i++) {
                $newName = $name . '-' . $i;
                if (!$node->childExists($newName)) {
                    return $newName;
                }
            }
            // Fell through all the way to here? That means we couldn't find a
            // unique name after way too many attempts.
            // Maybe you should have an exception instead.
            throw new DAV\Exception('Could not assign a unique name for "' . $name . '"');
        } else {
            return $name;
        }
    }
}
