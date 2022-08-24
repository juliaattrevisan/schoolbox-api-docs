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
use Schoolbox\User;
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
     * @api GET /pastoral/class/{folderId}/students
     * @since 22.1
     *
     * @param FolderModel $folder
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getReportDataForAllStudents(FolderModel $folder): void
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

        // Build a result
        $result = [];
        foreach ($students as $student) {
            $cursor = new Cursor(
                $this->pastoralDataSource,
                $this->getUser(),
                $student,
                $pastoralIds,
                $this->request->query->get('sort') ?? Cursor::SORT_OPTION_CREATED,
                $this->request->query->get('count') ?? Cursor::LIMIT_DEFAULT,
            );
            $cursoredRecords = $cursor->retrieveResults(null);
            $result[]        = [
                'student' => call_user_func($this->userTranslator, $student),
                'records' => [
                    'data' => $cursoredRecords->map($this->pastoralTranslator),
                    'metadata' => [
                        'count'  => $cursor->retrieveResultCount(),
                        'cursor' => [
                            'current' => $cursor->current($cursoredRecords),
                            'next'    => $cursor->next($cursoredRecords),
                        ],
                    ],
                ],
            ];
        }

        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $result,
                'metadata' => null,
            ]),
        );
    }

    /**
     * Retrieve more pastoral records for this student.
     *
     * @api GET /pastoral/class/{folderId}/student/{studentId}/data
     * @since 22.1
     *
     * @param FolderModel $folder
     * @param User        $student
     * @return void
     */
    public function getReportDataForStudent(FolderModel $folder, User $student): void
    {
    }
}
