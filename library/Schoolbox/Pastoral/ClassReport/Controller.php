<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter\Page\Model as FilterPageModel;
use Schoolbox\Resource\Folder\Model as FolderModel;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct(private IFactoryFactory $filterFactoryFactory)
    {
    }

    /**
     * @api GET /pastoral/class/{folderId}
     * @since 22.1
     */
    public function getReport(FolderModel $folder): void
    {
        $page        = FilterPageModel::PAGE_CLASS_PASTORAL;
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            $page,
            // The alias of the Aura route which loads the filtered content, if any.
            // If null, then the element's `filterContentLoaded` event is expected
            // to handle rendering of the filtered content.
            null,
            $this->getUser(),
            // If an Aura route is provided above: pass these parameters into it.
            [],
            // The query string params for this request:
            // this contains the actually selected filters.
            $this->request->query->getArrayCopy(),
        );

        $classCode = $folder->getCodeString();

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralClassReportModule/index.twig',
                'data'     => [
                    'folder'      => $folder,
                    'pageTitle'   => "Homepage ($classCode)",
                    'filterPanel' => $filterPanel,
                ]
            ]
        );
    }
}
