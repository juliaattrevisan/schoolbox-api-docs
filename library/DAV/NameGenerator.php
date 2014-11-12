<?php

/**
 * Helper class for generating unique names for a WebDAV node.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class DAV_NameGenerator
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
     * @throws Sabre\DAV\Exception if this method couldn't create a unique name
     *                             after 1000 attempts
     *
     * @param  Sabre\Dav\SimpleCollection $node The node to generate a child name for
     * @param  string                     $name The name to uniquify
     *
     * @return string
     */
    public function getUniqueChildName(Sabre\Dav\SimpleCollection $node, $name)
    {
        if ($node->childExists($name)) {
            for ($i = 1; $i <= 1000; $i++) {
                $new_name = $name . '-' . $i;
                if (!$node->childExists($new_name)) {
                    return $new_name;
                }
            }
            // Fell through all the way to here? That means we couldn't find a
            // unique name after way too many attempts.
            // Maybe you should have an exception instead.
            throw new Sabre\DAV\Exception('Could not assign a unique name for "' . $name . '"');
        } else {
            return $name;
        }
    }
}