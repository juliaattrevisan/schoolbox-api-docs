<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Process;

/**
 * Take a Group Attachment from schoolbox and set it up in Turnitin,
 * setting up the Group Attachment record and uploading the file contents
 * just like we do for File.php; since transmission can break at any step
 * we need a way to just force it in
 */
class GroupAttachment
{

    public function process()
    {
        $this->createGroupAttachment();

        $this->uploadFile();
    }

    public function createGroupAttachment() {}

    public function uploadFile() {}

    public function changeState() {}

    public function logWarning() {}

    public function logDebug() {}
}