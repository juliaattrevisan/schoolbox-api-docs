<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Web\Controller\Base;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\SortUsersByName;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;

/**
 * Controller for pastoral report page.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Pastoral\DataSource              $pastoralDataSource Used for fetching pastoral records, and applying
     *                                                             filtering appropriate to the current user.
     * @param UserAccessMapper                 $userAccessMapper   Used for finding the set of students in a folder.
     * @param UserTranslator                   $userTranslator     Used for converting a user model into an array, for
     *                                                             JSON output.
     * @param Pastoral\Translator\ModelToArray $pastoralTranslator Used for converting a pastoral model into an array,
     *                                                             for JSON output.
     * @param FilterHelper                     $filterHelper       Used for: building a filter panel, fetching a set of
     *                                                             pastoral IDs which match the filter panel.
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
     *
     * @param FolderModel $folder The folder to retrieve the report for.
     * @return void
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
                ],
            ]
        );
    }

    /**
     * Get report data as JSON.
     *
     * @api GET /pastoral/class/{folderId}/students
     * @since 22.1
     *
     * @param FolderModel $folder The folder to retrieve report data for.
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
            $result[] = [
                'student' => call_user_func($this->userTranslator, $student),
                'records' => $this->retrieveCursoredRecords(
                    $folder,
                    $student,
                    $pastoralIds,
                    null,
                    $this->request->query->get('sort', Cursor::SORT_OPTION_CREATED),
                    $this->request->query->getInt('count', Cursor::LIMIT_DEFAULT),
                ),
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
     * @param FolderModel $folder  The folder to retrieve records for:
     *                             `$student` must be a member of this class.
     * @param User        $student The student to retrieve records for.
     * @return void
     */
    public function getReportDataForStudent(FolderModel $folder, User $student): void
    {
        $pastoralIds = $this->filterHelper->findPastoralRecordIds(
            $this->getUser(),
            new GenericCollection(User::class, [$student]),
            $this->request->query->getArrayCopy(),
        );
        $this->response->content->set(
            $this->buildJsonOutput(
                $this->retrieveCursoredRecords(
                    $folder,
                    $student,
                    $pastoralIds,
                    $this->request->query->get('cursor'),
                    $this->request->query->get('sort', Cursor::SORT_OPTION_CREATED),
                    $this->request->query->getInt('count', Cursor::LIMIT_DEFAULT),
                )
            ),
        );
    }

    /**
     * Retrieve pastoral records and their metadata for a single student.
     *
     * @param FolderModel $folder        The folder to retrieve records for:
     *                                   used to create a link to the fetch
     *                                   records page for `$student`.
     * @param User        $student       The student to retrieve records for.
     * @param array       $pastoralIds   A pre-fetched set of pastoral IDs which
     *                                   match a provided filter: only records
     *                                   which match this ID set will be
     *                                   retrieved.
     * @param string|null $encodedCursor If provided: fetch records after this
     *                                   cursor.
     * @param string      $sort          The sort order to use: one of the
     *                                   `Cursor::SORT_OPTION_*` constants.
     * @param int         $count         The max number of records to fetch.
     * @return array
     */
    private function retrieveCursoredRecords(
        FolderModel $folder,
        User $student,
        array $pastoralIds,
        ?string $encodedCursor,
        string $sort,
        int $count,
    ): array {
        $cursor  = new Cursor(
            $this->pastoralDataSource,
            $this->getUser(),
            $student,
            $pastoralIds,
            $sort,
            $count,
        );
        $records = $cursor->retrieveResults($encodedCursor);
        return [
            'data'     => $records->map($this->pastoralTranslator),
            'metadata' => [
                'count'  => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $cursor->current($records),
                    'next'    => $cursor->next($records),
                ],
                '_links' => [
                    'report' => $this->getRouteIfAuthorized(
                        'pastoralClassReportModule.getReportDataForStudent',
                        [
                            'folder'    => $folder,
                            'folderId'  => $folder->getId(),
                            'student'   => $student,
                            'studentId' => $student->getId(),
                        ]
                    ),
                ],
            ],
        ];
    }
}
