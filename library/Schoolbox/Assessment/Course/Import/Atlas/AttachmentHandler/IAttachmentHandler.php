<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Interface for handling Atlas attachments, and attaching them to Schoolbox
 * folders.
 */
interface IAttachmentHandler
{
    /**
     * Attach this attachment to the given folder.
     *
     * @param Attachment $attachment The attachment to attach
     * @param Folder     $folder     Where it gets attached
     * @param bool       $private    Is it private (i.e. for teacher eyes only)?
     * @param User       $owner      Who owns it?
     * @throws MapperException if something goes wrong whilst attaching
     */
    public function attach(Attachment $attachment, Folder $folder, bool $private, User $owner): void;


    /**
     * Retrieves the default settings for this attachment.
     *
     * @param Attachment $attachment
     * @return array enabled: is this attachment enabled by default?
     *               private: is this attachment private by default?
     *                        (if not set, this doesn't support private)
     */
    public function getDefaultSettings(Attachment $attachment): array;
}
