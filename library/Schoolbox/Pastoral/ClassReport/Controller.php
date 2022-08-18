<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter\Page\Model as FilterPageModel;
use Schoolbox\Resource\Folder\Model as FolderModel;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct(
        private IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
    ) {
    }

    /**
     * @api GET /pastoral/class/{folderId}
     * @since 22.1
     */
    public function getReport(FolderModel $folder): void
    {
        $page        = FilterPageModel::PAGE_CLASS_PASTORAL;
        $params      = [
            'folder'   => $folder,
            'folderId' => $folder->getId(),
        ];
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            $page,
            $this->router->generate('pastoralClassReportModule.postReport', $params),
            $this->getUser(),
            $params,
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

    public function postReport(FolderModel $folder): void
    {
        $request = $this->request->post['filter_selection'] ?: [];
        $page    = FilterPageModel::PAGE_CLASS_PASTORAL;
        $codec               = new Codec($this->filterMapper, $this->filterSelectionMapper);
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);

//        $this->response->content->set(
//            [
//                'template' => '@pastoralClassReportModule/inner.twig',
//                'data'     => [
//                    'folder'      => $folder,
//                    'pageTitle'   => "Homepage ($classCode)",
//                    'filterPanel' => $filterPanel,
//                ]
//            ]
//        );
    }
}
