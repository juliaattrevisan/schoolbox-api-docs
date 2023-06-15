<?php

/**
 * Stub implementation of Community Portal connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ICommunityPortal;
use SensitiveParameter;

class CommunityPortal extends Base implements ICommunityPortal
{
    /**
     * @inheritDoc
     */
    public function changePassword($communityId, #[SensitiveParameter] $newPassword)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function setSecrets(#[SensitiveParameter] $key, #[SensitiveParameter] $initializationVector)
    {
    }
}
