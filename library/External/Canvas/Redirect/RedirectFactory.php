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

    /**
     * Return an instance of a redirect class, unique per school,
     * because everyone has their own naming convention which affects the redirect url.
     * If a school is using canvas but doesn't have its own redirect class, their redirect will silently not work.
     * @return AbstractRedirect|null
     */
    public function produce(): ?AbstractRedirect
    {
        $className = $this->canvasDb->isCanvasData2
            ? sprintf('Custom\\%s\\Redirect', ucfirst(strtolower($this->siteSchoolCode)))
            : sprintf('Custom\\%s\\Redirect1', ucfirst(strtolower($this->siteSchoolCode)));
        if (class_exists($className)) {
            // TRIGS and NEWIC are using this
            /** @var \Custom\Trigs\Redirect|\Custom\Newic\Redirect|\Custom\Trigs\Redirect1|\Custom\Newic\Redirect1 $className */
            return new $className(
                trim($this->canvasUrl),
                $this->canvasDb,
                $this->folderMapper
            );
        }
        return null;
    }
}
