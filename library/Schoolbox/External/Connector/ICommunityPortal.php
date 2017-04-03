<?php
/**
 * Interface for external database community portal classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
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
    function changePassword($communityId, $newPassword);

    /**
     * Sets the secret key and initialization vector required for encryption/decryption
     *
     * @param $key
     * @param $initializationVector
     */
    function setSecrets($key, $initializationVector);
}
