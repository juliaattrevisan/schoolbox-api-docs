<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Context\Folder;

use Alaress\Codec\ICodec;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Factory for building contexts from a folder.
 */
class ClaimFactory
{
    /**
     * Codec, for encoding resource link ID into a claim identifier.
     */
    private ICodec $codec;

    /**
     * Constructor
     *
     * @param ICodec $codec
     */
    public function __construct(ICodec $codec)
    {
        $this->codec = $codec;
    }

    /**
     * Build a context claim for a folder.
     *
     * @param Folder $folder
     * @return ContextClaim
     */
    public function buildForFolder(Folder $folder): ContextClaim
    {
        return new ContextClaim(
            (string)$this->codec->encode($folder->getId()),
            // I have seen no explanation for what context type actually is,
            // thus I will make something up.
            [ContextClaim::TYPE_COURSE_SECTION],
            $folder->getCodeString(),
            $folder->getTitle(),
        );
    }
}
