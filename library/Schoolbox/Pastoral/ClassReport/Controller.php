<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\Filter;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct(
        private PastoralMapper $pastoralMapper,
        private Filter\IFactoryFactory $filterFactoryFactory,
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
        $page        = Filter\Page\Model::PAGE_CLASS_PASTORAL;
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

    /**
     * @api POST /pastoral/class/{folderId}
     * @since 22.1
     */
    public function postReport(FolderModel $folder): void
    {
        $request             = $this->request->post['filter_selection'] ?: [];
        $page                = Filter\Page\Model::PAGE_CLASS_PASTORAL;
        $codec               = new Filter\Codec($this->filterMapper, $this->filterSelectionMapper);
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        // Hardcoded filter for folder: ensures only students in the given
        // folder get returned

        $dynamicFilters = $this->filterFactoryFactory->convertSelectionsToFilters(
            $selectionsPerFilter,
            $this->getUser(),
            $page,
        );

        $idFinder = Filter\Filters\Pastoral\IdFinder::getInstance(
            $this->filterMapper,
            $this->pastoralMapper,
        );
        $pastoralIds = $idFinder->findIdsByFilters($dynamicFilters, $this->getUser());


//        $pastoralIds = $this->

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

        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'status'  => 200,
                    'content' => $this->buildUnthemedHtmlOutput(
                        '@pastoralClassReportModule/inner.twig',
                        [
                            'pastoralIds' => $pastoralIds,
//                            'markbook'              => $markbook,
//                            'folder'                => $folder,
//                            'rank'                  => $rank,
//                            'rankEnabled'           => (bool)$this->getConfigLoader()->get('view_rank'),
//                            'writable'              => $this->getAssessmentsWritability($markbook->getAssessments()),
//                            'queryArgs'             => $codec->convertSelectionsToQueryString($selectionsPerFilter),
//                            'availableVisibilities' => Visibility::getAvailableVisibilities((int)$this->getConfigLoader()->get('submission_visibility_max')),
                        ])->getAsString(),
                ]
            )
        );
    }
}
