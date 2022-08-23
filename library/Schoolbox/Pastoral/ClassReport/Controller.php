<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\Filter;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\SortUsersByName;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct(
        private Pastoral\Mapper $pastoralMapper,
        private Pastoral\DataSource $pastoralDataSource,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private UserAccessMapper $userAccessMapper,
    ) {
    }

    /**
     * @api GET /pastoral/class/{folderId}
     * @since 22.1
     */
    public function getReport(FolderModel $folder): void
    {
        $page        = Filter\Page\Model::PAGE_CLASS_PASTORAL;
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            $page,
            null,
            $this->getUser(),
            [],
            $this->request->query->getArrayCopy(),
        );

        $classCode = $folder->getCodeString();

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralClassReportModule/index.twig',
                'data'     => [
                    'folder'      => $folder,
                    'pageTitle'   => $folder->getTitle(),
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

        // Get all students in class: add them to filter
        $students = $this->userAccessMapper
            ->findUsersInCategory($folder, Role::STUDENT)
            ->sortByCallback((new SortUsersByName())->getCallable(), true);
        $studentIds = $students->getPropertyValues('id');
        $hardCodedFilters = [
            Filter\Filters\Pastoral\Student\Definition::class => array_combine($studentIds, $studentIds),
        ];

        $idFinder = Filter\Filters\Pastoral\IdFinder::getInstance(
            $this->filterMapper,
            $this->pastoralMapper,
        );
        $pastoralIds = $idFinder->findIdsByFilters(
            array_merge($dynamicFilters, $hardCodedFilters),
            $this->getUser(),
        );
        $pastoralRecords          = $this->pastoralDataSource
            ->findByColumnList($this->getUser(), ['id' => $pastoralIds]);
        $pastoralRecordsByStudent = $pastoralRecords
            ->groupByCallback(
                function (Pastoral\Model $pastoral) {
                    return $pastoral->getStudent()->getId();
                }
            )
        ;

        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'status'  => 200,
                    'content' => $this->buildUnthemedHtmlOutput(
                        '@pastoralClassReportModule/inner.twig',
                        [
                            'students'                 => $students,
                            'pastoralRecordsByStudent' => $pastoralRecordsByStudent,
                        ]
                    )->getAsString(),
                ]
            )
        );
    }
}
