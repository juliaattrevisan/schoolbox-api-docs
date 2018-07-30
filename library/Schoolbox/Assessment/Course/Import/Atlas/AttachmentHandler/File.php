<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Handler for importation of files from Atlas to Schoolbox.
 */
class File
{
    public function attach(Folder $folder, Attachment $attachment, bool $private): bool
    {
        // This attachment needs
    }
}
