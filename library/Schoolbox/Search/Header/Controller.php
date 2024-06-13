<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\Search\Header;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Exception;
use Schoolbox\Assessment\Course\Import\Destinations;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Controller extends Base
{
    public const STATUS_OK = 200;
    public const STATUS_SERVER_ERROR = 500;

    /**
     * @param FolderMapper $folderMapper
     * @param Access       $access
     */
    public function __construct(
        private readonly FolderMapper $folderMapper,
        private readonly Access $access
    ) {
    }

    /**
     * Returns a JSON array of users and folders based on keyword
     *
     * @api GET /search/header
     */
    public function search(): void
    {
        $keyword = $this->requestQuery['keyword'];
        $result = [];
        $errorOccurred = false;

        if ($keyword !== null) {
            try {
                /** @var IConnector $db */
                $db = $this->di->get('databaseInternal');
                $searchService = new Search\Query($db, $this->getConfigLoader(), $this->getDi()->get('l10n'));
                $result = $searchService->fetchResults($keyword, $this->getUser());
            } catch (Exception $exception) {
                $errorOccurred = true;
                $result = [
                    'message' => $exception->getMessage()
                ];
            }
        }

        $this->response = $this->responseAsJson($result)->withStatus($errorOccurred ? self::STATUS_SERVER_ERROR : self::STATUS_OK);
    }

    /**
     * Search function. Retrieve a list of Folders that match the string in $_GET['query']
     *
     * Searches by both name and class code
     *
     * @api   GET /search/header/classes
     * @since 24.0
     * @throws MapperException
     */
    public function searchClasses(): void
    {
        $query = (string)$this->requestQuery->get('query');
        $folders = (new Destinations($this->folderMapper))->findImportable($query);

        /* Swap keys for selectize compatibility -_-
         */
        $data = [];

        foreach ($folders as $folder) {
            // Ignore folders with CourseSpecs
            if ($folder->getCourseSpecification() instanceof CourseSpecification\Model) {
                continue;
            }

            // Ignore folders with UnitSpecs
            if ($folder->getUnitSpecification() instanceof UnitSpecification\Model) {
                continue;
            }

            // Make sure the user has access to the folder before we return it
            if ($this->access->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_WRITE) {
                continue;
            }

            $data[] = [
                'id'   => $folder->getId(),
                'name' => $folder->getTitle(),
            ];
        }

        $this->response = $this->responseAsJson($data);
    }
}
