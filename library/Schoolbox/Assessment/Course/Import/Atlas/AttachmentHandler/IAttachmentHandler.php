<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Interface for handling Atlas attachments, and attaching them to Schoolbox
 * folders.
 */
interface IAttachmentHandler
{
    /**
     * Attach this attachment to the given folder.
     *
     * @param Attachment $attachment
     * @param Folder     $folder
     * @param bool       $private
     * @throws MapperException if something goes wrong whilst attaching
     */
    public function attach(Attachment $attachment, Folder $folder, bool $private): void;
}
