<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Class;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct(private FolderMapper $folderMapper)
    {
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper(): FolderMapper
    {
        return $this->folderMapper;
    }

    /**
     * @api GET /pastoral/class/{homepageId}
     * @since 22.1
     */
    public function getPastoralClassReport(FolderModel $homepageFolder): void
    {
        $classCode = $homepageFolder->getCodeString();

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralClass/index.twig',
                'data'     => [
                    'pageTitle' => "Homepage ($classCode)",
                    'breadcrumbs' => [
                        ["Homepage ($classCode)", "homepage/{$homepageFolder->getId()}"], ['Pastoral Report']
                    ]
                ]
            ]
        );
    }
}
