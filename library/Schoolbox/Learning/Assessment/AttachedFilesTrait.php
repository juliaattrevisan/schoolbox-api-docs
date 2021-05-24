<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

/**
 * Trait AttachedFilesTrait
 *
 * The accessor methods for Attached Files are shared between Due Work and Task,
 * as well as their Decorator instances
 *
 * @package Schoolbox\Learning\Assessment
 */
trait AttachedFilesTrait
{
    /**
     * @return array|string[]
     */
    protected function getAttachedFiles()
    {
        return $this->attachedFiles ?: [];
    }

    /**
     * @param array|string[] $attachedFiles
     * @return $this
     */
    protected function setAttachedFiles($attachedFiles)
    {
        $this->attachedFiles = $attachedFiles;
        return $this;
    }
}
