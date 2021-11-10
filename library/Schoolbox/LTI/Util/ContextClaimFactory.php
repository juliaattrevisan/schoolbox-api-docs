<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Factory for building contexts.
 */
class ContextClaimFactory
{
    /**
     * Build a context claim for a folder.
     *
     * @param Folder $folder
     * @return ContextClaim
     */
    public function buildForFolder(Folder $folder): ContextClaim
    {
        // TODO Context's identifier should be a FQDN, with a codec
        return new ContextClaim(
            (string)$folder->getId(),
            // I have seen no explanation for what context type actually is,
            // thus I will make something up.
            [ContextClaim::TYPE_COURSE_SECTION],
            $folder->getCodeString(),
            $folder->getTitle(),
        );
    }
}
