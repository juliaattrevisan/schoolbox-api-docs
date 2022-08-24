<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\SortUsersByName;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;

class Controller extends Base
{
    /**
     * Constructor
     */
    public function __construct(
        private Pastoral\DataSource $pastoralDataSource,
        private UserAccessMapper $userAccessMapper,
        private UserTranslator $userTranslator,
        private Pastoral\Translator\ModelToArray $pastoralTranslator,
        private FilterHelper $filterHelper,
    ) {
    }

    /**
     * Get report page.
     *
     * @api GET /pastoral/class/{folderId}
     * @since 22.1
     */
    public function getReport(FolderModel $folder): void
    {
        $filterPanel = $this->filterHelper->buildFilterPanel(
            $this->getUser(),
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
        // Save selected filters
        $this->filterHelper->saveSelections(
            $this->getUser(),
            $this->request->query->getArrayCopy(),
        );

        // Find all students in class
        $students = $this->userAccessMapper
            ->findUsersInCategory($folder, Role::STUDENT)
            ->sortByCallback((new SortUsersByName())->getCallable(), true);

        // Find all pastoral IDs for records matching those students
        $pastoralIds = $this->filterHelper->findPastoralRecordIds(
            $this->getUser(),
            $students,
            $this->request->query->getArrayCopy(),
        );

        // Push said records through the censor
        $pastoralRecords = $this->pastoralDataSource
            ->findByColumnList($this->getUser(), ['id' => $pastoralIds])
            ->sortBy('createdAt', Collection::ORDER_DESCENDING);
        /** @var Pastoral\Model[][] $pastoralRecordsByStudent */
        $pastoralRecordsByStudent = $pastoralRecords
            ->groupByCallback(
                function (Pastoral\Model $pastoral) {
                    return $pastoral->getStudent()->getId();
                }
            );

        // Build a result
        $result = [];
        foreach ($students as $student) {
            $pastoralRecordsForStudent = [];
            foreach ($pastoralRecordsByStudent[$student->getId()] ?? [] as $pastoralRecord) {
                $pastoralRecordsForStudent[] = call_user_func($this->pastoralTranslator, $pastoralRecord);
            }

            $result[] = [
                'student' => call_user_func($this->userTranslator, $student),
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
