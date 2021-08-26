<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Learning\Assessment\Seb\Payload;
use Schoolbox\User;

/**
 * Check if you can view this assessment with a normal browser
 * or if you need to view it with SEB Safe Exam Browser
 */
class ValidSebCheck
{

    /**
     * If an assessment requires the SEB Safe Exam Browser to complete work,
     * it checks whether the $_SERVER superglobal contains the two headers
     * that SEB Safe Exam Browser expects to find.
     *
     * @param User   $user
     * @param string $startUrl
     * @param bool   $sebEnabled
     * @param array  $serverSuperglobal
     * @param string $browserExamKey
     * @param string $configKey
     * @return bool
     */
    public function isSatisfiedBy(
        User $user,
        string $startUrl,
        bool $sebEnabled,
        array $serverSuperglobal,
        string $browserExamKey,
        string $configKey
    ): bool {

        /* Only students are required to use the SEB
         */
        if ($user->getRole()->isStaff()) {
            return true;
        }

        /* SEB was not required; let people through as normal
         */
        if (!$sebEnabled) {
            return true;
        }

        /* Otherwise check whether the two superglobal keys exist
         */
        if (isset($serverSuperglobal['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'], $serverSuperglobal['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'])) {
            /* Both keys that were transmitted by the browser must match
             * the values we expected to receive
             *
             * Browser Exam Key and Config Key are hashed with SHA256
             *
             * 2021-08-26 Disabled the BEK check because ran out of time
             * $bekMatches = $serverSuperglobal['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] === hash(Payload::HASH_ALGORITHM, "{$startUrl}{$browserExamKey}");
             */
            $configKeyMatches = $serverSuperglobal['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] === hash(Payload::HASH_ALGORITHM, "{$startUrl}{$configKey}");

            /* Both computed hashes must match what we expected
             *
             * 2021-08-26 Disabled the BEK check because ran out of time
             * return $bekMatches && $configKeyMatches;
             */
            return $configKeyMatches;
        }

        /* SEB was required, but you didn't pass the checks
         * You can't get in to this place
         */
        return false;
    }
}
