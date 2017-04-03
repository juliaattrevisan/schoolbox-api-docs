<?php
/**
 * Stub implementation of Community Portal connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ICommunityPortal;

class CommunityPortal extends Base implements ICommunityPortal
{
    /**
     * @inheritDoc
     */
    public function changePassword($communityId, $newPassword)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function setSecrets($key, $initializationVector)
    {
    }
}
