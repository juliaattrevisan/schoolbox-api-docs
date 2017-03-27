<?php
/**
 * Interface for external database community portal classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

interface CommunityPortalInterface
{
    /**
     * Updates the community portal password for this user.
     *
     * @param string $communityId
     * @param string $newPassword
     * @return bool
     */
    function changePassword($communityId, $newPassword);
}
