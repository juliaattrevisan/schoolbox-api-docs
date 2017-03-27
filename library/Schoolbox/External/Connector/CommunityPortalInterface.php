<?php
/**
 * Interface for external database community portal classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Alaress\Database\Connector\IConnector;

interface CommunityPortalInterface
{
    /**
     * Constructor
     *
     * @param array|IConnector $databaseExternal External database connector(s)
     * @param string $key The password encryption key. This must be secret and not stored in code.
     * @param string $initializationVector The hard-coded IV for the password. This does not need to be secret.
     */
    function __construct($databaseExternal, $key, $initializationVector);

    /**
     * Updates the community portal password for this user.
     *
     * @param string $communityId
     * @param string $newPassword
     * @return bool
     */
    function changePassword($communityId, $newPassword);
}
