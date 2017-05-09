<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Project;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Mapper;


/**
 * A factory for building 'Project' filter objects
 *
 * @see Filter\Filters\Assessment\Project\Definition for more detailed description
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /** @var Project\Mapper */
    private $projectMapper;

    /**
     * @var UnitImpl\Mapper
     */
    private $unitImplMapper;


    /**
     * @param Filter\Mapper    $filterMapper
     * @param Option\Mapper    $optionMapper
     * @param Defaults\Mapper  $defaultMapper
     * @param Selection\Mapper $selectionMapper
     * @param Folder\Mapper    $folderMapper
     * @param UnitImpl\Mapper  $unitImplMapper
     * @param Project\Mapper   $projectMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        UnitImpl\Mapper $unitImplMapper,
        Project\Mapper $projectMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper   = $folderMapper;
        $this->unitImplMapper = $unitImplMapper;
        $this->projectMapper  = $projectMapper;
    }


    /**
     * Return the set of projects that both:
     * * reside in the contexts set by admin
     * * are linked to folders taught by the $viewee
     *
     * @inheritdoc
     * @throws DefinitionException If unable to find a joined mapper
     * @throws Mapper\Exception If unable to hydrate a row
     */
    protected function buildExtraComponentArgs(User $user, $page, IFilterDefinition $definition, User $viewee = null)
    {
        return [
            'projects' => $this->getProjects($definition->getOptions($page), $page, $viewee),
        ];
    }


    /**
     * @param Collection|GenericCollection[]|Option\Model[][] $options
     * @param string                                          $page
     * @param User                                            $viewee
     * @return Collection|Project\Model[]
     * @throws DefinitionException If unable to find a joined mapper
     * @throws Mapper\Exception If unable to hydrate a row
     */
    private function getProjects(Collection $options, $page, User $viewee)
    {
        $projects = $this->projectMapper->createCollection();

        // Determine which folders we are ultimately interested in getting projects from:
        $onClassFolder             = $options[Definition::KEY_ON_CLASS_FOLDER][$page]->getAvailable();
        $onGroupFolder             = $options[Definition::KEY_ON_GROUP_FOLDER][$page]->getAvailable();
        $inUnitImplImportedToClass = $options[Definition::KEY_ON_CLASS_UNIT_IMPL][$page]->getAvailable();
        $inUnitImplImportedToGroup = $options[Definition::KEY_ON_GROUP_UNIT_IMPL][$page]->getAvailable();

        $getSubjects      = $onClassFolder || $inUnitImplImportedToClass;
        $groupAccessLevel = $onGroupFolder || $inUnitImplImportedToGroup ? UserAccess::ACCESS_WRITE : -1; // TODO - fix this dirty hack which is being used to prevent retrieval of group folders!

        // Are we not getting projects for either classes or groups?
        if (!$getSubjects && $groupAccessLevel !== UserAccess::ACCESS_WRITE) {
            // Bug out early.
            return $projects;
        }

        $involvedFolders = $this->folderMapper->findForUser($viewee, $getSubjects, $groupAccessLevel, true);

        // Divide folders into immediate contexts, and indirect contexts via a unit-impl
        /** @var GenericCollection $directFolders */
        $directFolders = $involvedFolders->filter(function (Folder\Model $folder) use ($onClassFolder, $onGroupFolder) {
            $isClass = $folder->getCodes()->count();
            $isGroup = $folder->getGroup();
            return ($isClass && $onClassFolder) || ($isGroup && $onGroupFolder);
        })->indexBy('id');

        /** @var GenericCollection $indirectFolders */
        $indirectFolders = $involvedFolders->filter(function (Folder\Model $folder) use ($inUnitImplImportedToClass, $inUnitImplImportedToGroup) {
            $isClass = $folder->getCodes()->count();
            $isGroup = $folder->getGroup();
            return $isClass && $inUnitImplImportedToClass || $isGroup && $inUnitImplImportedToGroup;
        })->indexBy('id');

        $this->fetchProjects($projects, $directFolders, $indirectFolders);

        return $projects;
    }


    /**
     * @param GenericCollection|Project\Model[] $projects
     * @param GenericCollection|Folder\Model[] $directFolders
     * @param GenericCollection|Folder\Model[] $indirectFolders
     * @return GenericCollection|Project\Model[]
     * @throws DefinitionException If unable to find a joined mapper
     * @throws Mapper\Exception If unable to hydrate a row
     */
    private function fetchProjects(GenericCollection $projects, GenericCollection $directFolders, GenericCollection $indirectFolders)
    {
        // Did we not find any relevant folder contexts ?
        if (0 === $directFolders->count() && 0 === $indirectFolders->count()) {
            // Bug out early.
            return $projects;
        }

        /** @var Assessment\Mapper $assessmentMapper */
        $assessmentMapper = $this->projectMapper->getJoinMapper('assessment');
        $folderCodeMapper = $this->folderMapper->getJoinMapper('codes');
        $groupMapper      = $this->folderMapper->getJoinMapper('groups');

        $folderCache   = $this->folderMapper->createCollection();
        $unitImplCache = $this->unitImplMapper->createCollection();
        $groupCache    = $groupMapper->createCollection();
        $codeCache     = $folderCodeMapper->createCollection();


        if ($directFolders->count()) {
            $builder = $this->folderMapper->getPredicateBuilder();
            $builder->equals('f.id', $directFolders->getColumnValues('id'));
            $placeholders = implode('AND', $builder->getWhere());

            $query = "
                SELECT 
                  {$this->projectMapper->getColumnString('p')},
                  {$assessmentMapper->getColumnString('a')},
                  {$this->folderMapper->getColumnString('f')},
                  {$folderCodeMapper->getColumnString('fc')},
                  {$groupMapper->getColumnString('g')}
                FROM {$this->projectMapper->getTable()} p
                JOIN {$this->projectMapper->getJoinSql('assessment', 'p', 'a')}
                JOIN {$assessmentMapper->getJoinSql('assessment_folder', 'a', 'f')}
                LEFT JOIN {$this->folderMapper->getJoinSql('codes', 'f', 'fc')}
                LEFT JOIN {$this->folderMapper->getJoinSql('groups', 'f', 'g')}
                WHERE {$placeholders}
            ";

            $statement = $this->projectMapper->getDatabase()->prepare($query);
            $statement->execute($builder->getParams());

            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $projectId      = $row['p.id'];
                $folderId       = $row['f.id'];
                $folderCodeCode = $row['fc.code'];
                $groupId        = $row['g.cid'];
                if(!$folderCache->offsetExists($folderId)){
                    $folderCache->setOffset($folderId, $this->folderMapper->hydrate($row, 'f'));
                }
                if(!$projects->offsetExists($projectId)){
                    $projects->setOffset($projectId, $this->projectMapper->hydrate($row, 'p'));
                    $projects[$projectId]->setAssessmentModel($assessmentMapper->hydrate($row, 'a'));
                    $projects[$projectId]->getAssessmentModel()->setContextFolder($folderCache[$folderId]);
                    $projects[$projectId]->setTitle($projects[$projectId]->getAssessmentModel()->getTitle());
                }
                if($folderCodeCode && !$codeCache->offsetExists($folderCodeCode)){
                    $codeCache->setOffset($folderCodeCode, $folderCodeMapper->hydrate($row, 'fc'));
                    $folderCache[$folderId]->getCodes()->setOffset($folderCodeCode, $codeCache[$folderCodeCode]);
                }
                if($groupId && !$groupCache->offsetExists($groupId)){
                    $groupCache->setOffset($groupId, $groupMapper->hydrate($row, 'g'));
                    $folderCache[$folderId]->setGroup($groupCache[$groupId]);
                }
            }
        }

        if ($indirectFolders->count()) {
            $builder = $this->folderMapper->getPredicateBuilder();
            $builder->equals('ci.folder_id', $indirectFolders->getColumnValues('id'));
            $placeholders = implode('AND', $builder->getWhere());

            $query = "
                SELECT
                  {$this->projectMapper->getColumnString('p')},
                  {$assessmentMapper->getColumnString('a')},
                  {$this->unitImplMapper->getColumnString('ui')},
                  {$this->folderMapper->getColumnString('f')},
                  {$folderCodeMapper->getColumnString('fc')},
                  {$groupMapper->getColumnString('g')}
                FROM {$this->projectMapper->getTable()} p
                JOIN {$this->projectMapper->getJoinSql('assessment', 'p', 'a')}
                JOIN {$assessmentMapper->getJoinSql('assessment_course_unit_implementation', 'a', 'ui')}
                JOIN {$this->unitImplMapper->getJoinSql('course', 'ui', 'ci')}
                JOIN {$this->unitImplMapper->getJoinMapper('course')->getJoinSql('folder', 'ci', 'f')}
                LEFT JOIN {$this->folderMapper->getJoinSql('codes', 'f', 'fc')}
                LEFT JOIN {$this->folderMapper->getJoinSql('groups', 'f', 'g')}
                WHERE {$placeholders}
            ";

            $statement = $this->projectMapper->getDatabase()->prepare($query);
            $statement->execute($builder->getParams());

            while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
                $projectId      = $row['p.id'];
                $unitImplId     = $row['ui.id'];
                $folderId       = $row['f.id'];
                $folderCodeCode = $row['fc.code'];
                $groupId        = $row['g.cid'];

                if(!$folderCache->offsetExists($folderId)){
                    $folderCache->setOffset($folderId, $this->folderMapper->hydrate($row, 'f'));
                }
                if(!$unitImplCache->offsetExists($unitImplId)){
                    $unitImplCache->setOffset($unitImplId, $this->unitImplMapper->hydrate($row, 'ui'));
                    $unitImplCache[$unitImplId]->setFolder($folderCache[$folderId]);
                }
                if(!$projects->offsetExists($projectId)){
                    $projects->setOffset($projectId, $this->projectMapper->hydrate($row, 'p'));
                    $projects[$projectId]->setAssessmentModel($assessmentMapper->hydrate($row, 'a'));
                    $projects[$projectId]->getAssessmentModel()->setContextUnitImplementation($unitImplCache[$unitImplId]);
                    $projects[$projectId]->setTitle($projects[$projectId]->getAssessmentModel()->getTitle());
                }
                if($folderCodeCode && !$codeCache->offsetExists($folderCodeCode)){
                    $codeCache->setOffset($folderCodeCode, $folderCodeMapper->hydrate($row, 'fc'));
                    $folderCache[$folderId]->getCodes()->setOffset($folderCodeCode, $codeCache[$folderCodeCode]);
                }
                if($groupId && !$groupCache->offsetExists($groupId)){
                    $groupCache->setOffset($groupId, $groupMapper->hydrate($row, 'g'));
                    $folderCache[$folderId]->setGroup($groupCache[$groupId]);
                }
            }
        }
        return $projects;
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        $projectIds = (new Collection($this->projectMapper->findAllIds(['deleted' => true])))->indexByCallback(function ($projectId) {
            return $projectId;
        });

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'projectIds' => $projectIds,
        ]);
    }
}
