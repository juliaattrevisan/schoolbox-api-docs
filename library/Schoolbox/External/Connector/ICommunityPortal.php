<?php

/**
 * Interface for external database community portal classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector;

interface ICommunityPortal
{
    /**
     * Updates the community portal password for this user.
     *
     * @param string $communityId
     * @param string $newPassword
     * @return bool
     */
    public function changePassword($communityId, $newPassword);

    /**
     * Sets the secret key and initialization vector required for encryption/decryption
     *
     * @param string $key
     * @param string $initializationVector
     */
    public function setSecrets($key, $initializationVector);
}
