<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Filter;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\Translator\ModelToArray;
use Schoolbox\Role;
use Schoolbox\SortUsersByName;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;

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
     * Get report data as JSON.
     *
     * @api GET /pastoral/class/{folderId}/data
     * @since 22.1
     */
    public function getReportData(FolderModel $folder): void
    {
        // Format: students, then their pastoral reports

        // Get all students in class
        $students = $this->userAccessMapper
            ->findUsersInCategory($folder, Role::STUDENT)
            ->sortByCallback((new SortUsersByName())->getCallable(), true);

        // Piece together the filter
        $request             = $this->request->query->get('filters', []);
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
        $studentIds       = $students->getPropertyValues('id');
        $hardcodedFilters = [
            Filter\Filters\Pastoral\Student\Definition::class => array_combine($studentIds, $studentIds),
        ];

        // Find pastoral records
        $idFinder = Filter\Filters\Pastoral\IdFinder::getInstance(
            $this->filterMapper,
            $this->pastoralMapper,
        );
        $pastoralIds = $idFinder->findIdsByFilters(
            array_merge($dynamicFilters, $hardcodedFilters),
            $this->getUser(),
        );
        $pastoralRecords          = $this->pastoralDataSource
            ->findByColumnList($this->getUser(), ['id' => $pastoralIds]);
        /** @var Pastoral\Model[][] $pastoralRecordsByStudent */
        $pastoralRecordsByStudent = $pastoralRecords
            ->groupByCallback(
                function (Pastoral\Model $pastoral) {
                    return $pastoral->getStudent()->getId();
                }
            );

        $studentTranslator  = new UserTranslator();
        $pastoralTranslator = new Pastoral\Translator\ModelToArray(
            $studentTranslator,
            new Pastoral\Comment\Translator\ModelToArray(
                $studentTranslator,
                $this->router,
                $this->authorizer,
                $this->getUser(),
                new ScrubAdapter(),
            ),
            new ModelToArray(new PrefixGenerator($this->getConfigLoader())),
            new ScrubAdapter(),
        );
        $result             = [];
        foreach ($students as $student) {
            $pastoralRecordsForStudent = [];
            foreach ($pastoralRecordsByStudent[$student->getId()] ?? [] as $pastoralRecord) {
                $pastoralRecordsForStudent[] = $pastoralTranslator($pastoralRecord);
            }

            $result[] = [
                'student' => $studentTranslator($student),
                'records' => $pastoralRecordsForStudent,
            ];
        }

        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $result,
                'metadata' => null,
            ]),
        );
    }
}
