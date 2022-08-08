<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Formatter;

use Schoolbox\User;

/**
 * Helper for formatting a user's name.
 */
class NameFormatter
{
    /**
     * Constructor
     *
     * @param string $format The format to use for formatting the user's name.
     *                       May contain the following replacement tokens:
     *                       * {title}:         the user's title, if they have one
     *                       * {firstname}:     the user's legal first name
     *                       * {preferredname}: the user's preferred name, if they have one
     *                       * {givenname}:     the user's preferred name, if they have one: otherwise their first name
     *                       * {lastname}:      the user's surname
     */
    public function __construct(private string $format)
    {
    }

    /**
     * Formats `$user` according to `$this->format`.
     *
     * Intended as a replacement for `\Schoolbox\User::formatName`.
     *
     * @param User $user
     * @return string
     */
    public function formatObject(User $user): string
    {
        return $this->formatArray([
            'title'          => $user->getTitle(),
            'firstname'      => $user->getFirstname(),
            'preferredname'  => $user->getPreferredName(),
            'preferred_name' => $user->getPreferredName(),
            'givenname'      => $user->getGivenName(),
            'lastname'       => $user->getLastname(),
        ]);
//        $format = $this->format;
//
//        /* Replace tags */
//        preg_match_all('|{\w+}|', $format, $tags);
//
//        foreach ($tags[0] as $tag) {
//            $key  = trim($tag, '{}');
//            $func = 'get' . ucfirst($key);
//            $value = '';
//            if (method_exists($user, $func)) {
//                if (is_string($user->$func())) {
//                    $value = $user->$func();
//                } else {
//                    $value = '';
//                }
//            }
//            $format = str_replace($tag, $value, $format);
//        }
//
//        if ($format = trim($format)) {
//            return $format;
//        } else {
//            return 'Unknown User';
//        }
    }

    /**
     * Formats `$userInfo` according to `$this->format`.
     *
     * Intended as a replacement for `\User::formatName`.
     *
     * @param array $userInfo An array representation of a user, with the
     *                        following possible keys:
     *                        * title:          the user's title, if they have one
     *                        * firstname:      the user's legal first name
     *                        * preferredname:  the user's preferred name, if they have one
     *                        * preferred_name: alias of "preferredname"
     *                        * givenname:      the user's preferred name, if they have one: otherwise their first name
     *                        *                 (derived from "preferredname" and "firstname" if not explicitly set)
     *                        * lastname:       the user's surname
     * @return string
     */
    public function formatArray(array $userInfo): string
    {
        $format = $this->format;

        /* Replace tags */
        preg_match_all('|{\w+}|', $format, $tags);
        if (empty($tags) || empty($userInfo)) {
            return 'Unknown User';
        }

        // Modify $userInfo to something which matches possible format:
        // * "preferred_name" and "preferredname" are the same
        // * "givenname" is "preferredname" if set, otherwise "firstname"
        if (isset($userInfo['preferred_name']) && !isset($userInfo['preferredname'])) {
            $userInfo['preferredname'] = $userInfo['preferred_name'];
        }
        $userInfo['givenname'] = (($userInfo['preferredname'] ?? '') !== '')
            ? $userInfo['preferredname']
            : ($userInfo['firstname'] ?? '');

        foreach ($tags[0] as $tag) {
            $key = trim($tag, '{}');

            if (isset($userInfo[$key]) && is_string($userInfo[$key])) {
                $value = $userInfo[$key];
            } else {
                $value = '';
            }
            $format = str_replace($tag, $value, $format);
        }
        if ($format = trim($format)) {
            return $format;
        } else {
            return 'Unknown User';
        }
    }
}
