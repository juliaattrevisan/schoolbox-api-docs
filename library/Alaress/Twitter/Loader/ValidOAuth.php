<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

/**
 * Class ValidOAuth
 *
 * Check that all four oauth credentials are present
 */
class ValidOAuth
{

    /**
     * Check that all four oauth credentials are present
     * They could be null if they were never set upon new installation
     * Or empty string if entered, then cleared, in the admin ui
     * Or an actual string value
     *
     * Connectivity using these credentials is a different problem
     *
     * @param null|string $consumerKey
     * @param null|string $consumerSecret
     * @param null|string $oauthToken
     * @param null|string $oauthSecret
     * @return bool
     */
    public function isSatisfiedBy(
        ?string $consumerKey,
        ?string $consumerSecret,
        ?string $oauthToken,
        ?string $oauthSecret
    ): bool
    {
        return (string)$consumerKey !== ''
            && (string)$consumerSecret !== ''
            && (string)$oauthToken !== ''
            && (string)$oauthSecret !== '';
    }
}
