<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\Project;

use function count;
use InvalidArgumentException;
use PDO;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Mapper;
use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Alaress\Database\Predicate;
use PDOException;
use UnderflowException;
use UnexpectedValueException;

/**
 * A factory for building 'Project' filter objects
 */
class Factory extends NullableContext\Factory
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
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Folder\Mapper                      $folderMapper
     * @param UnitImpl\Mapper                    $unitImplMapper
     * @param Project\Mapper                     $projectMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        UnitImpl\Mapper $unitImplMapper,
        Project\Mapper $projectMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper   = $folderMapper;
        $this->unitImplMapper = $unitImplMapper;
        $this->projectMapper  = $projectMapper;
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper()
        );
    }


    /**
     * Includes the set of projects that both:
     * * reside in the contexts set by admin
     * * are linked to folders taught by the $viewee
     *
     * @inheritDoc
     * @throws DefinitionException If unable to find a joined mapper
     * @throws InvalidArgumentException If $viewee is null
     * @throws Mapper\Exception If unable to hydrate a row
     * @throws PDOException
     * @throws Predicate\InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): Filter\IComponent {
        if (null === $viewee) {
            throw new InvalidArgumentException('$viewee cannot be null');
        }

        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $this->getProjects($pageFilter, $viewee, $routeArgs, $overrides)
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|NullableContext\KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $projectIds = (new Collection($this->projectMapper->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($projectId) {
                return $projectId;
            });
        return new NullableContext\KeyConverter($filter, $projectIds);
    }


    /**
     * @param Filter\Page\Model                                                $pageFilter
     * @param User                                                             $viewee
     * @param array                                                            $routeArgs
     * @param GenericCollection|GenericCollection[]|Filter\Selection\Model[][] $overrides Indexed by filter.id, selection.key
     * @return GenericCollection|Project\Model[]
     * @throws DefinitionException If unable to find a joined mapper
     * @throws Mapper\Exception If unable to hydrate a row
     * @throws PDOException
     * @throws Predicate\InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function getProjects(Filter\Page\Model $pageFilter, User $viewee, array $routeArgs, GenericCollection $overrides)
    {
        // Determine which folders we are ultimately interested in getting projects from:
        $options = $pageFilter->getOptions();

        // Is this filter being used on a page that renders involved folders?
        if ($this->isInvolvedFolderPage($pageFilter->getPage())) {
            [$directFolders, $indirectFolders] = $this->fetchRelatedFolders($options, $pageFilter, $viewee);
        } else {
            $directFolders   = $this->folderMapper->createCollection();
            $indirectFolders = $this->folderMapper->createCollection();
        }

        // Is component being used on a page that has a folder in the URL?
        if ($this->isSingleFolderPage($pageFilter->getPage())) {
            $folder                            = $this->findFolderModel($routeArgs);
            $directFolders[$folder->getId()]   = $folder;
            $indirectFolders[$folder->getId()] = $folder;
        }

        /* If we inserted an extra folder/s, we need to also insert any projects on that folder,
         * otherwise any assessments inside projects in the extra folder will never be visible.
         */
        $extraFolderIds = $this->parseFolderIds($overrides);
        if (count($extraFolderIds)) {
            // An inserted folder may be neither a class or group, so look for project
            // on it directly, and via an unitImpl.
            $extraFolders    = $this->folderMapper->findByColumn('id', $extraFolderIds);
            $directFolders   = $directFolders->mergeUnique($extraFolders, 'id');
            $indirectFolders = $indirectFolders->mergeUnique($extraFolders, 'id');
        }

        return $this->fetchProjects($directFolders, $indirectFolders);
    }


    /**
     * Returns the set of folders that the specified user is a teacher or instructor of,
     * filtering folders by admin-set config.
     *
     * @param GenericCollection|\Schoolbox\Filter\Page\Option\Model[] $options
     * @param Filter\Page\Model                                       $pageFilter
     * @param User                                                    $viewee
     * @return Collection|GenericCollection|Project\Model[]|Folder\Model[]
     */
    private function fetchRelatedFolders(GenericCollection $options, Filter\Page\Model $pageFilter, User $viewee): GenericCollection
    {
        $optionNone          = $options[Definition::KEY_NONE];
        $optionClassFolder   = $options[Definition::KEY_ON_CLASS_FOLDER];
        $optionGroupFolder   = $options[Definition::KEY_ON_GROUP_FOLDER];
        $optionClassUnitImpl = $options[Definition::KEY_ON_CLASS_UNIT_IMPL];
        $optionGroupUnitImpl = $options[Definition::KEY_ON_GROUP_UNIT_IMPL];

        if ($pageFilter->getUserConfigurable()) {
            $inClass                   = $optionClassFolder->getAvailable();
            $inGroup                   = $optionGroupFolder->getAvailable();
            $inUnitImplImportedToClass = $optionClassUnitImpl->getAvailable();
            $inUnitImplImportedToGroup = $optionGroupUnitImpl->getAvailable();
        } else {
            $roleType                  = $viewee->getRole()->getType();
            $inNone                    = $optionNone->getDefaults()[$roleType]->getSelected();
            $inClass                   = $optionClassFolder->getDefaults()[$roleType]->getSelected();
            $inGroup                   = $optionGroupFolder->getDefaults()[$roleType]->getSelected();
            $inUnitImplImportedToClass = $optionClassUnitImpl->getDefaults()[$roleType]->getSelected();
            $inUnitImplImportedToGroup = $optionGroupUnitImpl->getDefaults()[$roleType]->getSelected();

            // Are all available options unchecked ?
            if (!($inNone || $inClass || $inGroup || $inUnitImplImportedToClass || $inUnitImplImportedToGroup)) {
                // For consistency, this must logically equate to the filter not reducing the full set.
                $inClass = $inGroup = $inUnitImplImportedToClass = $inUnitImplImportedToGroup = true;
            }
        }
        // Which folders do we need to fetch ?
        $getClasses = $inClass || $inUnitImplImportedToClass;
        $getGroups  = $inGroup || $inUnitImplImportedToGroup;

        $projects = $this->projectMapper->createCollection();

        // Are we not getting projects for either classes or groups?
        if (!$getClasses && !$getGroups) {
            // Bug out early.
            return $projects;
        }

        // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
        $groupAccessLevel = $getGroups ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_FULL + 1;

        $involvedFolders = $this->folderMapper->findForUser($viewee, $getClasses, $groupAccessLevel, false);

        // Divide folders into immediate contexts, and indirect contexts via a unit-impl
        /** @var GenericCollection $directFolders */
        $directFolders = $involvedFolders->filter(function (Folder\Model $folder) use ($inClass, $inGroup) {
            $isClass = $folder->isClass() || $folder->wasClass();
            $isGroup = $folder->getGroup();
            return ($isClass && $inClass) || ($isGroup && $inGroup);
        })->indexBy('id');

        /** @var GenericCollection $indirectFolders */
        $indirectFolders = $involvedFolders->filter(function (Folder\Model $folder) use ($inUnitImplImportedToClass, $inUnitImplImportedToGroup) {
            $isClass = $folder->isClass() || $folder->wasClass();
            $isGroup = $folder->getGroup();
            return ($isClass && $inUnitImplImportedToClass) || ($isGroup && $inUnitImplImportedToGroup);
        })->indexBy('id');

        return [$directFolders, $indirectFolders];
    }


    /**
     * @param GenericCollection|Folder\Model[] $directFolders
     * @param GenericCollection|Folder\Model[] $indirectFolders
     * @return GenericCollection|Project\Model[]
     * @throws DefinitionException If unable to find a joined mapper
     * @throws Mapper\Exception If unable to hydrate a row
     * @throws Predicate\InvalidColumnException
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function fetchProjects(GenericCollection $directFolders, GenericCollection $indirectFolders)
    {
        $projects = $this->projectMapper->createCollection();
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
            $builder->in('f.id', $directFolders->getColumnValues('id'));
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

            /** @noinspection PhpAssignmentInConditionInspection */
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $projectId      = $row['p.id'];
                $folderId       = $row['f.id'];
                $folderCodeCode = $row['fc.code'];
                $groupId        = $row['g.cid'];
                if (!$folderCache->offsetExists($folderId)) {
                    $folderCache->setOffset($folderId, $this->folderMapper->hydrate($row, 'f')->setCodes($folderCodeMapper->createCollection()));
                }
                if (!$projects->offsetExists($projectId)) {
                    $projects->setOffset($projectId, $this->projectMapper->hydrate($row, 'p'));
                    $projects[$projectId]->setAssessmentModel($assessmentMapper->hydrate($row, 'a'));
                    $projects[$projectId]->getAssessmentModel()->setContextFolder($folderCache[$folderId]);
                }
                if ($folderCodeCode && !$codeCache->offsetExists($folderCodeCode)) {
                    $codeCache->setOffset($folderCodeCode, $folderCodeMapper->hydrate($row, 'fc'));
                    $folderCache[$folderId]->getCodes()->setOffset($folderCodeCode, $codeCache[$folderCodeCode]);
                }
                if ($groupId && !$groupCache->offsetExists($groupId)) {
                    $groupCache->setOffset($groupId, $groupMapper->hydrate($row, 'g'));
                    $folderCache[$folderId]->setGroup($groupCache[$groupId]);
                }
            }
        }

        if ($indirectFolders->count()) {
            $builder = $this->folderMapper->getPredicateBuilder();
            $builder->in('ci.folder_id', $indirectFolders->getColumnValues('id'));
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

            /** @noinspection PhpAssignmentInConditionInspection */
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $projectId      = $row['p.id'];
                $unitImplId     = $row['ui.id'];
                $folderId       = $row['f.id'];
                $folderCodeCode = $row['fc.code'];
                $groupId        = $row['g.cid'];

                if (!$folderCache->offsetExists($folderId)) {
                    $folderCache->setOffset($folderId, $this->folderMapper->hydrate($row, 'f')->setCodes($folderCodeMapper->createCollection()));
                }
                if (!$unitImplCache->offsetExists($unitImplId)) {
                    $unitImplCache->setOffset($unitImplId, $this->unitImplMapper->hydrate($row, 'ui'));
                    $unitImplCache[$unitImplId]->setFolder($folderCache[$folderId]);
                }
                if (!$projects->offsetExists($projectId)) {
                    $projects[$projectId] = $this->projectMapper->hydrate($row, 'p');
                    $projects[$projectId]->setAssessmentModel($assessmentMapper->hydrate($row, 'a'));
                    $projects[$projectId]->getAssessmentModel()->setContextUnitImplementation($unitImplCache[$unitImplId]);
                }
                if ($folderCodeCode && !$codeCache->offsetExists($folderCodeCode)) {
                    $codeCache->setOffset($folderCodeCode, $folderCodeMapper->hydrate($row, 'fc'));
                    $folderCache[$folderId]->getCodes()->setOffset($folderCodeCode, $codeCache[$folderCodeCode]);
                }
                if ($groupId && !$groupCache->offsetExists($groupId)) {
                    $groupCache->setOffset($groupId, $groupMapper->hydrate($row, 'g'));
                    $folderCache[$folderId]->setGroup($groupCache[$groupId]);
                }
            }
        }
        return $projects;
    }
}
