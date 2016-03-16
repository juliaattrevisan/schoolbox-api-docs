<?php
/**
 * Offline stub implementation of community portal connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Offline\Base;

use Schoolbox\External\Connector\CommunityPortalInterface;

class CommunityPortal implements CommunityPortalInterface
{
    /**
     * @inheritDoc
     */
    public function changePassword($communityId, $newPassword)
    {
    }
}
