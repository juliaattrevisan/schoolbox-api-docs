<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Folder\Model as FolderModel;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct()
    {
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
                    'folder'      => $homepageFolder,
                    'pageTitle'   => "Homepage ($classCode)"
                ]
            ]
        );
    }
}
