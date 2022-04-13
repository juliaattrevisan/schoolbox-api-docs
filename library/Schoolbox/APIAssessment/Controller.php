<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIAssessment;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\YearLevel\Mapper;

class Controller extends Base
{

    protected IConnector $dbMapper;
    protected Helper $assessmentHelper;
    /**
     * Controller constructor.
     *
     */
    public function __construct(Mapper $mapper)
    {
        $this->assessmentHelper = new Helper($mapper);
    }

    /**
     * Returns an assessment object
     *
     * @api /api/assessment/{assessmentId}
     * @link TODO:
     */
    public function apiAssessmentGet(string $assessmentId)
    {
        if (!is_numeric($assessmentId)) {
            $problem = (new ApiProblem('Assessment not found, ID must be a number'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        //get all assessment info for object
        $output = $this->assessmentHelper->getAssessment($assessmentId);

        /**
         * build the whole thing
         */
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * @api GET /api/assessment
     * @link TODO
     */
    public function apiAssessmentSearch()
    {
        $search = $this->request->query->get('search', '');
        $filter = json_decode($this->request->query->get('filter', ''), true) ?? null;
        $cursor = (int)$this->request->query->get('cursor', null);
        $limit = (int)$this->request->query->get('limit', 5);
        if ('' === $search) {
            $problem = (new ApiProblem('Search must not be blank'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }
        if (null === $filter) {
            $problem = (new ApiProblem('Filter must not be blank'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }
        if (strlen($search) <= 2) {
            $problem = (new ApiProblem('Search term must be longer than 2 characters'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }

        $output = [];
        $params = [];


        $select  = "
SELECT
    assessment.id,
    assessment.title,
    lu_work_type.name as `workTypeName`,
    lu_work_type.id as `workTypeId`,
    v_assessment_relation.folder_id as `folderId`,
    assessment.weight as `weighted`,
    assessment.due_date,
    GROUP_CONCAT( lu_year_level.id ORDER BY lu_year_level.id) as `yearLevelId`,
    GROUP_CONCAT( lu_year_level.name ORDER BY lu_year_level.id) as `yearLevelName`
";
        $from = "FROM v_assessment_relation
JOIN assessment on v_assessment_relation.assessment_id = assessment.id
LEFT JOIN lu_work_type on assessment.work_type_id = lu_work_type.id
LEFT JOIN folder_year_level on v_assessment_relation.folder_id = folder_year_level.folder_id
LEFT JOIN lu_year_level on folder_year_level.year_level_id = lu_year_level.id

";
        $where = "
WHERE
    assessment.deleted_at is null
";
        if (array_key_exists('assessmentId', $filter)) {
            $where .= " AND v_assessment_relation.assessment_id = :assessmentId ";
            $params['assessmentId'] = $filter['assessmentId'];
        }
        if (array_key_exists('title', $filter)) {
            $where .= " AND assessment.title = :title ";
            $params['title'] = $filter['title'];
        }
        if (array_key_exists('contextId', $filter)) {
            $where .= " AND v_assessment_relation.folder_id = :contextId ";
            $params['contextId'] = $filter['contextId'];
        }
        if (array_key_exists('weighted', $filter)) {
            $where .= " AND assessment.weight";
            if ($filter['weighted'] === true) {
                $where .= " != 0.00 ";
            } else {
                $where .= " = 0.00 ";
            }
        }
        if (array_key_exists('workType', $filter)) {
            if (array_key_exists('id', $filter['workType'])) {
                $where .= " AND assessment.work_type_id = :workTypeID ";
                $params['workTypeID'] = $filter['workType']['id'];
            } elseif (array_key_exists('name', $filter['workType'])) {
                $where .= " AND lu_work_type.name = :workTypeName ";
                $params['workTypeName'] = $filter['workType']['name'];
            }
        }
        if (array_key_exists('dueDate', $filter)) {
            if (array_key_exists('from', $filter['dueDate'])) {
                $where             .= " AND due_date >= :dueFrom ";
                $params['dueFrom'] = $filter['dueDate']['from'];
            }
            if (array_key_exists('to', $filter['dueDate'])) {
                $where             .= " AND due_date <= :dueTo ";
                $params['dueTo'] = $filter['dueDate']['to'];
            }
        }
        if (array_key_exists('yearLevel', $filter)) {
            if (array_key_exists('id', $filter['yearLevel'])) {
                $where .= " AND lu_year_level.id = :yearLevelId ";
                $params['yearLevelId'] = $filter['yearLevel']['id'];
            } elseif (array_key_exists('name', $filter['yearLevel'])) {
                $where .= " AND lu_year_level.name = :yearLevelName ";
                $params['yearLevelName'] = $filter['yearLevel']['name'];
            }
        }

        if (0 !== $cursor) {
            $where .= " AND assessment.id > :cursor ";
        }
        $where .= "ORDER BY assessment.id";
        if (0 !== $limit) {
            $where .= " LIMIT :limit";
        }
        $mapper = UserMapper::getInstance()->getDatabase();
        $statement = $mapper->prepare($select . $from . $where);
        if (0 !== $limit) {
            $lim = $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        if (0 !== $cursor) {
            $cur = $statement->bindValue('cursor', $cursor, PDO::PARAM_INT);
        }
        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }
        $statement->execute();
        /**
         * build the whole thing
         */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $weighted = false;
            if ('0.00' !== $row['weighted']) {
                $weighted = true;
            }
            $yearLevel = null;
            if (null !== $row['yearLevelName']) {
                $names = explode(',', $row['yearLevelName']);
                $ids = explode(',', $row['yearLevelId']);
                $yearLevel = [];
                foreach ($names as $id => $name) {
                    $yearLevel[] = [
                        'id'    => $ids[$id],
                        'name'    => $name,
                    ];
                }
            }
            $output[] = [
                'assessmentId'  => $row['id'],
                'title'         => $row['title'],
                'workType'      => [
                                    'name'  => $row['workTypeName'],
                                    'id'    => $row['workTypeId']
                                ],
                'yearLevel'     => $yearLevel,
                'folderId'      => $row['folderId'],
                'weighted'      => $weighted,
                'due_date'      => $row['due_date'],

            ];
        }


        /**
         * now we have the user id's that match the search result, create the standard user array with hydration.
         */

        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'data'  =>  $output,
                    'metadata'  => [
                        'count' => count($output),
                        'cursor' => end($output)['id'] ?? null
                    ]
                ]
            )
        );
    }
}
