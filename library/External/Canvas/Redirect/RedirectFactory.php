<?php

/**
 *
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Redirect;

use External\Canvas\Importer\CanvasDb;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Returns homepage redirect for Canvas integration
 */
class RedirectFactory
{
    public function __construct(
        private string $siteSchoolCode,
        private string $canvasUrl,
        private CanvasDb $canvasDb,
        private FolderMapper $folderMapper
    ) {
    }

    public function produce(): ?AbstractRedirect
    {
        $className = sprintf('Custom\\%s\\Redirect', ucfirst(strtolower($this->siteSchoolCode)));
        if (class_exists($className)) {
            return new $className(
                trim($this->canvasUrl),
                $this->canvasDb,
                $this->folderMapper
            );
        }
        return null;
    }
}
