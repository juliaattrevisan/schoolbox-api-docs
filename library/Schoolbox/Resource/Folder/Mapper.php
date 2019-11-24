<?php
/**
 * Mapper
 *
 * Eventually migrate CategoryMapper into here
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress;
use InvalidArgumentException;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use UnexpectedValueException;
use function array_key_exists;
use function count;
use function explode;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\NullLogger;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Resource\AbstractFolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use \Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Subscription\Loader;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder\Search\Result;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Code as FolderCode;
use Schoolbox\YearLevel;
use Alaress\Database\Predicate;
use Schoolbox\Error;
use function strnatcasecmp;

/**
 * Class Mapper
 *
 * SBFS Folder Mapper. Maps folder table to SBFS folder objects.
 *
 * @package Schoolbox\Resource\Folder
 * @method static self getInstance()
 * @method Model createModel($idValue = null)
 * @method Collection|Model[] findByColumnList($columnList, $options = []);
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findAll($options = [])
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection|Model[] findByPredicates(PredicateBuilder $pb, array $options = []) *
 */
class Mapper extends AbstractFolderMapper implements ILocator, Searchable
{

    /**
     * Interface with external user database.
     *
     * @var External_UserInterface
     */
    protected $external_user;


    /**
     * @inheritdoc
     *
     * @throws \Schoolbox\Mapper\Exception
     * @throws DefinitionException
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasModel(Model::class);
        $this->hasTable('folder');

        $this->uses('inherit', self::TYPE_BOOLEAN);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('colour', self::TYPE_COLOR);

        // Joins:
        $this->joins('image_hash', Storage\Mapper::class, 'hash', null, null, 'coverImage', 'coverImage');

        $this->hasJoin(
            'codes', array(
                'multiple'  => true,
                'mapper'    => Code\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->joinsVia(
            'folder_year_level',
            YearLevel\Mapper::class,
            YearLevel\Mapper::COL_ID,
            'year_level_id',
            'folder_id',
            null,
            null,
            null,
            'yearLevels'
        );

        $this->hasJoin(
            'homepages', array(
                'multiple'  => false,
                'mapper'    => HomepageConfigMapper::class,
                'this_key'  => 'id',
                'other_key' => 'cid',
            )
        );

        $this->hasJoin(
            'files', array(
                'multiple'  => true,
                'mapper'    => File\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'category',
            )
        );

        $this->hasJoin(
            'groups', array(
                'multiple'  => false,
                'mapper'    => GroupMapper::class,
                'this_key'  => 'id',
                'other_key' => 'cid',
            )
        );

        $this->hasJoin(
            'course_specification', array(
                'multiple'  => false,
                'mapper'    => CourseSpecification\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->hasJoin(
            'unit_specification', array(
                'multiple'  => false,
                'mapper'    => UnitSpecification\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->joinsVia(
            'curriculum_node_for_category',
            Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'category_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateFolderCodes']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateUnit']);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateInheritedPermissions']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateFolderCodes']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'unMuteClassNotifications']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateCourseSpecification']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'refreshNotificationRedirects']);

        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'hardDeleteCategoryCodes']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCategoryCodes']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteFiles']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteLinks']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCourseSpecification']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteUnitSpecification']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCourseImplementations']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteAssessments']);
    }

    /**
     * Validate a unit hasn't been moved into the root folder
     *
     * @param Model $folder
     * @return bool
     */
    public function validateUnit(Model $folder)
    {
        // Units cannot be put in root folder

        if ($folder->hasUnitSpecification() && $folder->getParent()->getId() == 0) {
            return false;
        }

        // Other folders always get a clean bill of health

        return true;
    }

    /**
     * Update a units course specification. If its parent is not a course this will make it a course
     *
     *     ||
     *     ||
     *     ||
     *     ||
     *     ||
     *     ||
     *     ||     ...enjoy cleaning up those resource browser drag & drop course creation messes!
     *     ||
     *    /||\
     *   /||||\
     *   ======         __|__
     *   ||||||        / ~@~ \
     *   ||||||       |-------|
     *   ||||||       |_______|
     *
     * @param Model $folder
     * @param Model $previous
     * @return bool
     */
    public function updateCourseSpecification(Model $folder, $previous = null)
    {
        // If the folder is a unit, we need to make sure its course specification is right

        if ($folder->hasUnitSpecification()) {

            // We need to check if parent is a course, but first we need the damn thing hydrated

            $parent = $this->findById($folder->getParent()->getId());

            if ($parent->hasCourseSpecification()) {

                // It's a course, just use the existing specification

                $courseSpecification = $parent->getCourseSpecification();

            } else {

                // It's not a course, we need to make a new one... yippee!

                /** @var CourseSpecification\Model $courseSpecification */
                $courseSpecification = CourseSpecification\Mapper::getInstance()->createModel();;
                $courseSpecification->setFolder($parent);
                CourseSpecification\Mapper::getInstance()->save($courseSpecification);

                // Now we plonk that new course onto the parent folder

                $parent->setCourseSpecification($courseSpecification);
                $this->save($parent);
            }

            // And finally update the actual folder we're supposed to be looking at

            $folder->getUnitSpecification()->setCourse($courseSpecification);
            UnitSpecification\Mapper::getInstance()->save($folder->getUnitSpecification());

            // We didn't find a course specification- this unit isn't where its supposed to be

            return false;
        }

        // Wasn't a unit being updated, just move on

        return true;
    }


    /**
     * @param Model $folder
     * @param Model $previous
     */
    public function refreshNotificationRedirects(Model $folder, $previous = null)
    {
        if ((!$previous && $folder->getInherit()) || ($previous && $folder->getInherit() !== $previous->getInherit())) {
            // folder is new AND inheritance is enabled OR inheritance has changed

            // TODO desingleton these
            $redis = Alaress\Redis::getClientInstance();
            $log = new NullLogger();

            // update the cache
            $cache = new WriteCache($redis, $log, new Loader\Factory($redis, $log));

            $cache->cacheRedirects(INSTANCE_ID);
        }
    }

    /**
     * Post soft-delete callback to soft-delete the CourseSpec linked to this Folder if one exists
     *
     * Intentionally no corresponding 'postRestore' callback.
     *
     * @param Model $folder
     * @return bool
     * @throws DefinitionException If specified join not defined
     */
    public function softDeleteCourseSpecification(Model $folder)
    {
        /** @var CourseSpecification\Mapper $courseSpecMapper */
        $courseSpecMapper = $this->getJoinMapper('course_specification');
        $courseSpec = $courseSpecMapper->findByColumn('folder_id', $folder->getId(), ['deleted' => true]);
        $courseSpecMapper->delete($courseSpec);
        return true;
    }

    /**
     * Post soft-delete callback to soft-delete any CourseImpls linked to this Folder
     *
     * Intentionally no corresponding 'postRestore' callback.
     *
     * @param Model $folder
     * @return bool
     */
    public function softDeleteCourseImplementations(Model $folder)
    {
        /** @var CourseImplementation\Mapper $courseImplMapper */
        $courseImplMapper = CourseImplementation\Mapper::getInstance();
        $courseImpls = $courseImplMapper->findByColumn('folder_id', $folder->getId(), ['deleted' => true]);
        $courseImplMapper->delete($courseImpls);
        return true;
    }

    /**
     * Post soft-delete callback to soft-delete the UnitSpec linked to this Folder if one exists
     *
     * Intentionally no corresponding 'postRestore' callback.
     *
     * @param Model $folder
     * @return bool
     * @throws DefinitionException If specified join not defined
     */
    public function softDeleteUnitSpecification(Model $folder)
    {
        /** @var UnitSpecification\Mapper $unitSpecMapper */
        $unitSpecMapper = $this->getJoinMapper('unit_specification');
        $unitSpec = $unitSpecMapper->findByColumn('folder_id', $folder->getId(), ['deleted' => true]);
        $unitSpecMapper->delete($unitSpec);
        return true;
    }

    /**
     * Pre soft-delete callback to soft-delete the Assessments, created inside this Folder
     *
     * @param Model $folder
     *
     * @return bool
     * @throws MapperException
     */
    public function softDeleteAssessments(Model $folder)
    {
        $assessmentMapper = AssessmentMapper::getInstance();

        // Silence notifications on assessments, as the cascading from folder
        // to assessment causes way too many retractions to handle (SA-8034)
        $oldPropagator    = $assessmentMapper->getEventPropagator();
        $mockPropagator   = new MockPropagator();
        $assessmentMapper->setEventPropagator($mockPropagator);

        // Ensure the propagator is set back how it was after deleting
        try {
            $assessmentMapper->delete($assessmentMapper->findByColumn('v_f.folder_id', $folder->getId()));
        } finally {
            $assessmentMapper->setEventPropagator($oldPropagator);
        }
        return true;
    }

    /**
     * Log a page view
     *
     * @param Model $category
     */
    public function logPageView(Model $category) {

        if(!$category->isRootNode()) {
            // new mechanism for storing pageviews
            // the use of NOW() generates a truncation warning (non-fatal and non-critical) since a datetime is being
            // shoved into a date field, but it can be safely ignored: the data inserts correctly
            $query = "
                INSERT INTO homepage_visits (`date`, `user_group_id`, `category_id`)
                VALUES (NOW(), :gid, :cid)
                ON DUPLICATE KEY
                UPDATE `hitcount` = `hitcount` + 1
            ";

            $params = array(
                'cid' => $category->getId(),
                'gid' => $_SESSION['gid']
            );

            $statement = $this->database->prepare($query);
            $statement->execute($params);
        }
    }

    /**
     * Find all images for a category
     *
     * @deprecated use File\Mapper::findForFolders() directly
     * @param Model $folder
     * @param int $limit
     * @return File\Model[]|Collection
     * @throws DefinitionException If specified join not defined
     */
    public function findImages(Model $folder, $limit = 0)
    {
        /** @var File\Mapper $fileMapper */
        $fileMapper = $this->getJoinMapper('files');

        $limit = (int)$limit;

        $opts = [ 'filter' => 'image' ];

        if ($limit) {
            $opts['size'] = $limit;
        }

        return $fileMapper->findForFolders([ $folder->getId() ], $opts);
    }

    /**
     * Pre-save callback to ensure a folder's codes are valid
     *
     * @param Model $model
     * @return bool
     * @throws DefinitionException If specified join not defined
     * @throws Predicate\InvalidColumnException
     * @throws SchoolboxException
     */
    protected function validateFolderCodes(Model $model): bool
    {
        if ($model->getCodes() === null) {
            return true;
        }
        /** @var Code\Mapper $codeMapper */
        $codeMapper = $this->getJoinMapper('codes');
        if(!(new ValidCodesSpec($codeMapper))->isSatisfiedBy($model)){
            throw Error\Mapper::getInstance()->generateException(ErrorMapper::ERROR_CLASS_CODE_EXISTS);
        }

        return true;
    }

    /**
     * Pre-save callback: ensures that folders can't be their own parents
     *
     * @param Model $model
     *
     * @return bool
     */
    public function checkParentage($model)
    {
        // Ensure $model isn't its own parent
        try {
            $this->findParents($model);
        } catch (\OverflowException $e) {
            return false;
        }
        return true;
    }

    /**
     * Is $possibleDescendant a descendant of $possibleAncestor?
     *
     * @param Model $possibleDescendant The folder that may be a descendant of $possibleAncestor
     * @param Model $possibleAncestor
     * @return bool
     */
    public function isDescendant(Model $possibleDescendant, Model $possibleAncestor)
    {
        // All folders are descendant of pseudo-root
        if (0 === $possibleAncestor->getId()) {
            return true;
        }

        foreach ($this->findParents($possibleDescendant, null, true) as $ancestor) {
            if ($ancestor->getId() == $possibleAncestor->getId()) {
                return true;
            }
        }
        return false;
    }


    /**
     * PostSave callback to clear User- + Role-based permissions if they are now being inherited
     *
     * @param Model $model
     * @param null|Model $previous
     * @return bool
     * @throws DefinitionException If specified join not defined
     */
    protected function updateInheritedPermissions(Model $model, Model $previous = null): bool
    {
        // If we are inheriting permissions delete any previously created group + user perms
        if ($previous && $model->getInherit()) {

            // Delete the group
            $this->getJoinMapper('groups')->deleteByColumn('cid', $model->getId());

            // Delete group memberships
            UserAccessMapper::getInstance()->deleteByColumnList([
                "sid"     => $model->getId(),
                "service" => UserAccess::SERVICE_HOMEPAGE,
            ]);

            // Delete role permissions
            RoleAccessMapper::getInstance()->deleteByColumn('cid', $model->getId());
        }
        return true;
    }


    /**
     * PostSave callback to efficiently save the folder's folderCodes
     * @param Model      $model
     * @param Model|null $previous
     * @return bool
     * @throws DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    protected function updateFolderCodes(Model $model, Model $previous = null): bool
    {
        // Don't update if join not hydrated
        if ($model->getCodes() === null) {
            return true;
        }

        /** @var Code\Mapper $folderCodeMapper */
        $folderCodeMapper = $this->getJoinMapper('codes');

        // No prior codes if this is a CREATE.
        $oldCodes = $previous
            ? $folderCodeMapper->findByColumn('folder_id', $model->getId())
            : $folderCodeMapper->createCollection();

        // Don't save any codes if folder is inheriting permissions
        $newCodes = $model->getInherit()
            ? $folderCodeMapper->createCollection()
            : $model->getCodes();

        $comparator = static function(Code\Model $left, Code\Model $right){
            return strnatcasecmp($left->getCode(), $right->getCode()) === 0;
        };

        $codesToRemove = $oldCodes->diff($newCodes, $comparator);
        if ($codesToRemove->count()) {
            $folderCodeMapper->delete($codesToRemove);
        }

        // Save any class codes that have been added to folder
        $codesToAdd = $newCodes->diff($oldCodes, $comparator);
        if ($codesToAdd->count()) {
            $folderCodeMapper->save($codesToAdd);
        }
        return true;
    }

    /**
     * Post-save callback to ensure that if this folder is a class, that its notifications are not muted
     *
     * @param Model $model
     * @param Model $previous
     * @return bool
     */
    public function unMuteClassNotifications($model, $previous = null)
    {
        // only execute if folder has codes and not inheriting permissions
        if (!$model->getInherit() && $model->getCodes() && $model->getCodes()->count()) {

            // remove any mutes that might exist on this subject (classes are not allowed to be muted)
            RerouteOverrideMapper::getInstance()->deleteByColumnList([
                'delivery'      => RoutePermission::MUTE,
                'msg_route_key' => "service.1.{$model->getId()}"
            ]);
        }

        return true;
    }

    /**
     * Hard deletes all category codes that belong to this object.
     *
     * Called as a post delete callback.
     *
     * @param Model $object
     * @throws DefinitionException If specified join not defined
     */
    public function hardDeleteCategoryCodes(Model $object)
    {
        $codeMapper = $this->getJoinMapper('codes');
        $codeMapper->hardDelete($codeMapper->findByColumn('folder_id', $object->getId()));
    }

    /**
     * Soft deletes all category codes that belong to this object.
     *
     * Called as a post soft-delete callback.
     *
     * @param Model $object
     * @throws DefinitionException If specified join not defined
     */
    public function softDeleteCategoryCodes(Model $object)
    {
        $codeMapper = $this->getJoinMapper('codes');
        $codeMapper->softDelete($codeMapper->findByColumn('folder_id', $object->getId()));
    }

    /**
     * Soft deletes all files that belong to this Folder model
     *
     * Called as a post soft-delete callback.
     *
     * @param Model $model
     * @throws DefinitionException If specified join mapper not found
     */
    public function softDeleteFiles(Model $model)
    {
        $mapper = $this->getJoinMapper('files');
        $mapper->softDelete($mapper->findByColumn('category', $model->getId()));
    }

    /**
     * Soft deletes all links that belong to this Folder model
     *
     * Called as a post soft-delete callback.
     *
     * @param Model $model
     */
    public function softDeleteLinks(Model $model)
    {
        $mapper = LinkMapper::getInstance();
        $mapper->softDelete($mapper->findByColumn('category', $model->getId()));
    }



    /**
     * Get a list of folders which belong to a particular parent
     *
     * It should only get the children of $id and probably should be
     * established in Ancestry
     *
     * @param int $id
     * @return GenericCollection|Model[]
     */
    public function findByParent($id)
    {
        $builder = $this->getPredicateBuilder();
        $builder->equals("{$this->getTable()}.parent", $id);
        $options = ['order' => ["{$this->getTable()}.name" => 'ASC']];

        return $this->findByPredicates($builder, $options);
    }

    /**
     * Returns a collection of all folders that are children of the specified ID (with optional recursion)
     *
     * @param int $id
     * @param bool $recurse
     * @return Collection|Model[]
     */
    public function findAllChildren($id, $recurse = false)
    {
        $collection = $this->createCollection();

        // Find all children of this node

        $children = $this->findByColumn('parent', $id);

        foreach ($children as $child) {
            $collection->add($child);

            // And optionally all of its children

            if ($recurse) {
                $kids = $this->findAllChildren($child->getId(), true);
                foreach ($kids as $kid) {
                    $collection->add($kid);
                }
            }
        }

        // Return what we got

        return $collection;
    }


    /**
     * Search for folders with the given curriculum code(s)
     *
     * @param string[] $codes
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function findByCurriculumCode(array $codes)
    {
        if (0 === count($codes)) {
            // no code? no matches
            return $this->createCollection();
        }

        $builder = $this->getPredicateBuilder()->in('cn.code', $codes);

        return $this->findByPredicates($builder);
    }

    /**
     * Helper method for creating a search query with codes, homepage config and
     * group all built in.
     *
     * You can add a WHERE statement at the end if you want.
     *
     * @return string
     * @throws DefinitionException If any specified join is not defined
     */
    protected function getSelectString()
    {
        $code_mapper           = $this->getJoinMapper('codes');
        $yearLevelMapper       = $this->getJoinMapper('folder_year_level');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper          = $this->getJoinMapper('groups');
        $courseSpecMapper      = $this->getJoinMapper('course_specification');
        $unitSpecMapper        = $this->getJoinMapper('unit_specification');


        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('coverImage', 'ci')},
                {$this->getJoinColumnString('coverImage.allowedFileExtension', 'cim')},
                {$code_mapper->getColumnString('cc')},
                {$yearLevelMapper->getColumnString('yl')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')},
                {$courseSpecMapper->getColumnString('cs')},
                {$unitSpecMapper->getColumnString('us')},
                {$this->getJoinColumnString('curriculum_node_for_category', 'cn')},
                {$this->getJoinColumnString('curriculum_node_for_category.level', 'cnl')},
                {$this->getJoinColumnString('curriculum_node_for_category.level.source', 'cnls')},
                {$this->getJoinColumnString('curriculum_node_for_category.area', 'cna')},
                {$this->getJoinColumnString('curriculum_node_for_category.area.source', 'cnas')}
        ";
    }


    /**
     * It seems we're going down this road...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('codes', '', 'cc')}
            LEFT JOIN {$this->getJoinSql('folder_year_level', '', 'yl')}
            LEFT JOIN {$this->getJoinSql('coverImage', '', 'ci', true)}
            LEFT JOIN {$this->getJoinSql('coverImage.allowedFileExtension', 'ci', 'cim', true)}
            LEFT JOIN {$this->getJoinSql('homepages', '', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', '', 'ug')}
            LEFT JOIN {$this->getJoinSql('course_specification', '', 'cs')}
            LEFT JOIN {$this->getJoinSql('unit_specification', '', 'us')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category', '', 'cn')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.level', 'cn', 'cnl')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.level.source', 'cnl', 'cnls')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.area', 'cn', 'cna')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.area.source', 'cna', 'cnas')}
        ";
    }


    /**
     * Search by name
     *
     * @param string $keyword
     * @param bool   $userGroupsOnly
     * @param array  $folderIds
     * @return Collection|Model[]
     * @throws Alaress\Database\Predicate\InvalidColumnException
     * @throws DefinitionException if join $name isn't on this mapper
     * @throws \UnderflowException
     */
    public function searchByName($keyword, $userGroupsOnly = false, array $folderIds = [])
    {
        $builder = $this->getPredicateBuilder();

        // Filter result set further by only returning records that are also in the userGroups table
        if($userGroupsOnly){
            $builder->notNull('ug.cid');
        }

        $subBuilder = $this->getPredicateBuilder();
        $subBuilder
            ->like("{$this->getTable()}.name", "%{$this->database->escapeForLike($keyword)}%")
            ->like('cc.code', "%{$this->database->escapeForLike($keyword)}%");

        $builder->any($subBuilder);

        /* If a list of folder ids was provided to restrict the set of results, use it
         */
        if (count($folderIds)) {
            $builder->in('id', $folderIds);
        }

        $options = ['order' => ["{$this->getTable()}.name" => 'ASC']];

        $folders = $this->findByPredicates($builder, $options);

        // Need to load folder codes again to ensure siblings of any matched code are inserted
        /** @var FolderCode\Mapper $codeMapper */
        $codeMapper = $this->getJoinMapper('codes');

        $folderIds      = $folders->getColumnValues($this->getIdentifierColumn());
        $folderCodes    = $codeMapper->findByColumn('folder_id', $folderIds);
        $codesPerFolder = $folderCodes->groupByCallback(function(FolderCode\Model $code){
            return $code->getFolder()->getId();
        });

        foreach($codesPerFolder as $folderId => $codes){
            $folders[$folderId]->setCodes($codes);
        }

        return $folders;
    }


    /**
     * Helper method for hydrating a result from a query created by getFindQuery().
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     * @throws MapperException if statement returned no category
     * @throws DefinitionException If specified join does is not defined
     * @throws UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var HomepageConfigMapper $homepageConfigMapper */
        $homepageConfigMapper = $this->getJoinMapper('homepages');
        /** @var GroupMapper $groupMapper */
        $groupMapper          = $this->getJoinMapper('groups');
        /** @var CourseSpecification\Mapper $courseSpecMapper */
        $courseSpecMapper     = $this->getJoinMapper('course_specification');
        /** @var UnitSpecification\Mapper $unitSpecMapper */
        $unitSpecMapper       = $this->getJoinMapper('unit_specification');
        /** @var FolderCode\Mapper $codeMapper */
        $codeMapper           = $this->getJoinMapper('codes');
        /** @var YearLevel\Mapper $yearLevelMapper */
        $yearLevelMapper      = $this->getJoinMapper('folder_year_level');
        /** @var Node\Mapper $curriculumNodeMapper */
        $curriculumNodeMapper = $this->getJoinMapper('curriculum_node_for_category');
        /** @var Storage\Mapper $coverImageMapper */
        $coverImageMapper     = $this->getJoinMapper('coverImage');
        /** @var MimetypeMapper $coverImageMimeMapper */
        $coverImageMimeMapper = $coverImageMapper->getJoinMapper('allowedFileExtension');

        $folders = $this->createCollection();

        /**
         * Light-weight cache for efficiency, indexed by YearLevel.id
         * @var YearLevel\Model[] $yearLevelCache
         */
        $yearLevelCache = [];

        // Track which joins we need to hydrate.
        $hydrateCodes           = false;
        $hydrateYearLevels      = false;
        $hydrateCurriculumNodes = false;
        $hydrateHomepage        = false;
        $hydrateGroup           = false;
        $hydrateCourseSpec      = false;
        $hydrateUnitSpec        = false;
        $hydrateCoverImage      = false;
        $hydrateCoverImageMime  = false;
        $firstRow               = true;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            // Examine the first row to determine what joins to hydrate
            if ($firstRow) {
                $hydrateCodes           = array_key_exists('cc.code', $row);
                $hydrateYearLevels      = array_key_exists('yl.' . YearLevel\Mapper::COL_ID, $row);
                $hydrateCurriculumNodes = array_key_exists('cn.id', $row); // TODO this probably needs to be more granular
                $hydrateHomepage        = array_key_exists('hp.cid', $row);
                $hydrateGroup           = array_key_exists('ug.cid', $row);
                $hydrateCourseSpec      = array_key_exists('cs.id', $row);
                $hydrateUnitSpec        = array_key_exists('us.id', $row);
                $hydrateCoverImage      = array_key_exists('ci.hash', $row);
                $hydrateCoverImageMime  = array_key_exists('cim.fileTypeId', $row);
                $firstRow               = false;
            }

            $folderId = (int)$row['id'];

            // create if not exists
            if (!$folders->offsetExists($folderId)) {
                $folders[$folderId] = $this->hydrate($row);

                // Instantiate collections we are about to populate
                if ($hydrateCodes) {
                    $folders[$folderId]->setCodes($codeMapper->createCollection());
                }
                if ($hydrateYearLevels) {
                    $folders[$folderId]->setYearLevels($yearLevelMapper->createCollection());
                }
                if ($hydrateCurriculumNodes) {
                    $folders[$folderId]->setCurriculumNodes($curriculumNodeMapper->createCollection());
                }
                // Hydrate reverse joins if present in row
                if ($hydrateHomepage) {
                    $folders[$folderId]->setHomepage($homepageConfigMapper->hydrate($row, 'hp'));
                }
                if ($hydrateGroup) {
                    $folders[$folderId]->setGroup($groupMapper->hydrate($row, 'ug'));
                }
                if ($hydrateCourseSpec) {
                    $folders[$folderId]->setCourseSpecification($courseSpecMapper->hydrate($row, 'cs'));
                }
                if ($hydrateUnitSpec) {
                    $folders[$folderId]->setUnitSpecification($unitSpecMapper->hydrate($row, 'us'));
                }
                if ($hydrateCoverImage) {
                    $coverImage = $coverImageMapper->hydrate($row, 'ci');
                    if ($coverImage && $hydrateCoverImageMime) {
                        $coverImageMimeType = $coverImageMimeMapper->hydrate($row, 'cim');
                        if ($coverImageMimeType) {
                            $coverImage->setMimetype($coverImageMimeType);
                        }
                    }
                    $folders[$folderId]->setCoverImage($coverImage);
                }
            }

            // Folder Codes: Ensure we de-duplicate (as we have joined multiple 1:N relationships)
            if ($hydrateCodes) {
                $folderCode = $codeMapper->hydrate($row, 'cc');
                if ($folderCode) {
                    $folders[$folderId]->getCodes()[$folderCode->getCode()] = $folderCode;
                }
            }

            // Year Level usages: Ensure we de-duplicate (as we have joined multiple 1:N relationships)
            if ($hydrateYearLevels) {
                $yearLevelId = $row['yl.' . YearLevel\Mapper::COL_ID];
                if ($yearLevelId) {
                    if (!array_key_exists($yearLevelId, $yearLevelCache)) {
                        $yearLevelCache[$yearLevelId] = $yearLevelMapper->hydrate($row, 'yl');
                    }
                    $folders[$folderId]->getYearLevels()->setOffset($yearLevelId, $yearLevelCache[$yearLevelId]);
                }
            }

            // Hydrate curriculum node codes (avoiding duplicates)
            // BTW fetching multiple 1-M relations in a single query
            // is how you get ants!
            // TODO replace ant infestation with `\Schoolbox\Curriculum\Node\Mapper::findForOtherMapper`
            if ($hydrateCurriculumNodes) {
                $curriculumNode = $curriculumNodeMapper->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls');
                if ($curriculumNode) {
                    $folders[$folderId]->getCurriculumNodes()[$curriculumNode->getId()] = $curriculumNode;
                }
            }
        }

        return $folders;
    }


    /**
     * Returns the first folder found that uses any of the codes specified in $code
     *
     * Does not find codes that have been deleted
     *
     * Note that 'ENG34' will match both 'ENG34' and 'SEN/ENG34', but 'SEN/ENG34' will not match 'ENG34'
     *
     * Hence, if you have a campus code available, you should attempt to use that first, only falling back to the
     * non-prefixed form if no campus-prefixed match is found.
     *
     * @param string $classCodeCsv May have campus prefix, eg 'J/ENG34,S/ENG43' or 'ABC', or 'J/ENG34'
     * @return Model
     * @throws MapperException If statement returned no category
     * @throws PDOException
     * @throws Predicate\InvalidColumnException
     */
    public function findByCode(string $classCodeCsv): Model
    {
        $folders = $this->findAllByCode($classCodeCsv);

        // The set of $code returned exactly one folder (determinate)
        // return this folder because we're sure
        if ($folders->count() === 1) {
            return $folders->first();
        }

        // no folders match the $code provided
        if ($folders->count() === 0) {
            throw new MapperException();
        }

        // Multiple folders resulted from $code
        // this is an edge case that cannot happen normally because business logic prevents it
        // you have to mangle the db, or provide bad data on the url to manifest this situation
        // In either case, pop out an exception because this should never have occurred

        // Soften exception to a warning, but log so we can investigate
        trigger_error("Multiple folders found for code", E_USER_WARNING);

        return $folders->first();
    }


    /**
     * Returns all folders that use any of the individual codes included in $classCodeCsv
     *
     * Does not match soft-deleted folder codes
     * Does not return soft-deleted folders or folder codes
     *
     * @param string $classCodeCsv Comma-separated list of class codes, eg 'J/ENG34,S/ENG43' or 'ABC', or 'J/ENG34'
     * @return GenericCollection|Model[]
     * @throws Predicate\InvalidColumnException
     * @throws PDOException
     */
    private function findAllByCode(string $classCodeCsv): GenericCollection
    {
        $classCodes = explode(',', $classCodeCsv);
        if (!count($classCodes)) {
            return $this->createCollection();
        }

        $codePb = $this->getPredicateBuilder();
        foreach ($classCodes as $classCode) {
            // Always return exact matches
            $codePb->equals('fc.code', $classCode);
            $codePb->like('fc.code', "%/{$this->database->escapeForLike($classCode)}");
        }

        $folderPb = $this->getPredicateBuilder()
                   ->isNull('f.deleted_at')
                   ->isNull('fc.deleted_at')
                   ->any($codePb);

        $pb = $this->getPredicateBuilder()->all($folderPb);

        // Dont bother fetching attributes to hydrate, as this query will be missing codes
        // that don't match the code query
        $query = "
            SELECT f.id
            FROM folder f
            JOIN folder_code fc ON fc.folder_id = f.id
            WHERE {$pb->getWhere()[0]}
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());

        $folderIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        // Retrieve all codes for folder, not just those specified by $classCodeCsv
        return $this->findByColumn($this->getIdentifierColumn(), $folderIds);
    }


    /**
     * Returns the Schoolbox folder (if it exists) that matches the SIS campus + class code.
     *
     * If multiple folders are found (which is an invalid system state), logs to sentry but just returns the first match.
     *
     * @param null|string $sisCampus
     * @param string $sisClass Will never have the campus code embedded inside it.
     * @return Model|null
     */
    public function findForCampusAndClass(?string $sisCampus, string $sisClass): ?Model
    {
        if ($sisClass === '') {
            return null;
        }

        // Need to support schools who do not include the campus code in schoolbox folder code.
        // This is a common workflow where schools have only a single campus.
        // Validation exists to ensure that if they are using this form, then
        // they cannot add the campus-prefixed form as well.
        // @see $this->validateFolderCodes()

        // If a campus code was provided, prefer exact match
        if ($sisCampus !== null && $sisCampus !== '') {
            $folderCodes = ["$sisCampus/$sisClass", $sisClass];
        } else {
            $folderCodes = [$sisClass];
        }

        $fcCodeBindings = Predicate\Builder::getBindings($folderCodes, 'code');
        $sql            = "
            SELECT f.id, fc.code
            FROM folder f
            JOIN folder_code fc on f.id = fc.folder_id
            WHERE f.deleted_at IS NULL AND fc.deleted_at IS NULL AND fc.code IN ({$fcCodeBindings->getPlaceholder()})";

        $rows = $this->database->query($sql, $fcCodeBindings->getParams())->fetchAll(PDO::FETCH_NUM);

        if (!count($rows)) {
            return null;
        }

        // The first in $folderCodes is preferred.
        // Codes are case-insensitive.
        foreach ($folderCodes as $folderCode) {
            foreach ($rows as [$rowFolderId, $rowFolderCode]) {
                if (strtolower($folderCode) === strtolower($rowFolderCode)) {
                    try {
                        $folder = $this->findById($rowFolderId);
                    } catch (Exception $e) {
                        return null;
                    }
                    $folder->setMatchedCode($rowFolderCode);
                    return $folder;
                }
            }
        }
        return null;
    }


    /**
     * Finds all folders for the given subject list.
     *
     * Designed to work with the output from `\External_User::getSubjectList`.
     *
     * @param array $subjectList Contains "code" and "campusCode" keys
     * @return GenericCollection|Model[]
     */
    public function findAllBySubjectList($subjectList)
    {
        $whereParts = [];
        $params     = [];
        $i          = 0;

        // Build the set of where parts for this subject list
        foreach (array_values($subjectList) as $subject) {
            $code       = $subject['code'];
            $campusCode = $subject['campusCode'];

            $whereParts[]        = "cc.code = :code_{$i}";
            $params["code_{$i}"] = $code;
            if (strlen($campusCode) === 0) {
                $whereParts[]             = "cc.code LIKE :fullCode_{$i}";
                $params[":fullCode_{$i}"] = '%/' . $this->database->escapeForLike($code);
            } else {
                $whereParts[]             = "cc.code = :fullCode_{$i}";
                $params[":fullCode_{$i}"] = $campusCode . '/' . $code;
            }

            $i++;
        }

        // Abort if no subjects
        if (empty($whereParts)) {
            return $this->createCollection();
        }

        // Query, hydrate and return
        $where = implode(' OR ', $whereParts);
        $query = "
            {$this->getSelectString()}
            {$this->getFromString()}
            WHERE folder.deleted_at IS NULL
            AND ($where)
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $folders = $this->hydrateSelect($statement);

        // Reload codes for retrieved folders.
        // The above query will not have fetched any codes that weren't in $subjectList.
        /** @var GenericCollection[]|FolderCode\Model[][] $foldersCodes */
        $foldersCodes = $this->getJoinMapper('codes')
                            ->findByColumn('folder_id', $folders->getColumnValues('id'))
                            ->groupByCallback(function (Code\Model $code) {
                                return $code->getFolder()->getId();
                            });
        foreach($foldersCodes as $folderId => $folderCodes){
            foreach($folderCodes as $code){
                $folders[$folderId]->getCodes()[$code->getCode()] = $code;
            }
        }
        return $folders;
    }

    /**
     * Finds a list of category codes based on an archived category code.
     *
     * Expects the name of the category contains $code between [] at the end.
     *
     * @param  string $code
     * @throws MapperException if statement returned no category
     * @return Collection|Model[]
     */
    public function findAllByArchivedCode($code)
    {
        // Don't need no category codes here
        $query = "
            {$this->getSelectString()}
            {$this->getFromString()}
            WHERE {$this->getTable()}.name LIKE :code
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'code' => '%[%' . $this->database->escapeForLike($code) . '%]',
            )
        );
        return $this->hydrateList($statement);
    }


    /**
     * @param int $id
     * @return Model
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception
     * @throws DefinitionException
     */
    public function findById($id): Model
    {
        // special case: 0 === root, but not in db
        // TODO localise setName()
        if ($id === Model::ROOT_ID || $id === (string)Model::ROOT_ID) {
            return $this->createModel()
                        ->setId(IFolder::ROOT_ID)
                        ->setName('Top Level')
                        ->setParent($this->createModel()->setId(Model::ROOT_PARENT_ID))
                        ->setInherit(false)
                        ->setCodes($this->getJoinMapper('codes')->createCollection())
                        ->setYearLevels($this->getJoinMapper('folder_year_level')->createCollection())
                        ->setCurriculumNodes($this->getJoinMapper('curriculum_node_for_category')->createCollection());
        }

        /** @var Model $folder */
        $folder = parent::findById($id);

        return $folder;
    }


    /**
     * Generates the prepared statement for usage in findFolderIdsForUser() and findForUser()
     *
     * Joins onto:
     *  * Homepage
     *  * Folder codes
     *  * Group
     *
     * @param User $user
     * @param bool $get_subjects
     * @param int  $access_level
     * @param bool $exactAccess
     * @return PDOStatement
     * @throws DefinitionException
     */
    private function findForUserQuery(User $user, bool $get_subjects, int $access_level, bool $exactAccess): PDOStatement
    {
        $query = "
            SELECT
                {$this->getColumnString('c')},
                {$this->getJoinColumnString('homepages', 'h')},
                {$this->getJoinColumnString('codes', 'cc')},
                {$this->getJoinColumnString('groups', 'g')}
            FROM {$this->getTable()} c
            INNER JOIN userAccess a ON c.id = a.sid AND a.service = :service AND a.uid = :user
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'h')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'g')}
            WHERE c.deleted_at IS NULL
        ";
        if ($exactAccess) {
            $query .= ' AND a.type = :access';
        } else {
            $query .= ' AND a.type >= :access';
        }
        $params = array(
            'user'    => $user->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'access'  => $access_level,
        );

        // TODO Currently incorrectly shows all classes for Staff, regardless of requested access. See SA-7364
        if ($this->fetchClassesFromExternalSis($get_subjects, $user, $access_level, $exactAccess)) {
            $external_user = $this->getExternalUser();
            if ($external_user instanceof External_UserInterface) {
                $subject_list = $external_user->getSubjectList($user);
                if (is_array($subject_list) && !empty($subject_list)) {
                    $category_codes = array_map(
                        function ($row) {
                            return trim($row['code']);
                        }, $subject_list
                    );


                    // Fetch these folders with a UNION of two optimized queries
                    // instead of one query. (see SA-7958)
                    // (Lesson: when LEFT JOINing onto a table I optionally
                    //  filter on, consider a UNION instead)
                    $subjectQuery = "
                        SELECT
                            {$this->getColumnString('c')},
                            {$this->getJoinColumnString('homepages', 'h')},
                            {$this->getJoinColumnString('codes', 'cc')},
                            {$this->getJoinColumnString('groups', 'g')}
                        FROM {$this->getTable()} c
                        INNER JOIN {$this->getJoinSql('codes', 'c', 'cc')}
                        LEFT JOIN {$this->getJoinSql('homepages', 'c', 'h')}
                        LEFT JOIN {$this->getJoinSql('groups', 'c', 'g')}
                        WHERE c.deleted_at IS NULL
                    ";
                    $subjectQueryParts = [];
                    if (!empty($category_codes)) {
                        $subjectQueryParts[] = 'cc.code IN ' . $this->getArrayPlaceholders($category_codes, 'code');
                        $params = array_merge($params, $this->getArrayParameters($category_codes, 'code'));

                        // Allow search of codes with a campus prefix.
                        $category_like_codes = array_map(
                            function ($code) {
                                return '%/' . $code;
                            },
                            $category_codes
                        );
                        foreach ($category_like_codes as $i => $like_code) {
                            $subjectQueryParts[] = 'cc.code LIKE :like_code_' . $i;
                            $params['like_code_' . $i] = $like_code;
                        }
                    }

                    if (!empty($subjectQueryParts)) {
                        $subjectQuery .= ' AND (' . implode(' OR ', $subjectQueryParts) . ')';
                        $query .= ' UNION ' . $subjectQuery;
                    }
                }
            }
        }

        $query .= ' ORDER BY `c.id` ASC';
        $statement = $this->database->prepare($query);
        $statement->execute($params);

        return $statement;
    }


    /**
     * Synonymous with findForUser() but returns you a scalar list of unique folder ids instead
     *
     * @param User $user
     * @param bool $get_subjects
     * @param int  $access_level
     * @param bool $exactAccess
     * @return array
     * @throws DefinitionException
     */
    public function findFolderIdsForUser(
        User $user,
        $get_subjects = true,
        $access_level = UserAccess::ACCESS_READ,
        $exactAccess = false
    ): array {
        $statement = $this->findForUserQuery($user, $get_subjects, $access_level, $exactAccess);

        $ids = [];

        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $ids[$row['c.id']] = $row['c.id'];
        }

        return array_keys($ids);
    }


    /**
     * Port of \Group->involvedInCategoryList() method.
     *
     * @param User $user The user to search for.
     * @param bool $get_subjects If false, subjects aren't retrieved.
     * @param int $access_level Defaults to UserAccess::ACCESS_READ, but may be
     *                           any access level.
     * @param bool $exactAccess If true, $access_level must be exact
     * @param bool $requireHomepage  If true, groups which do not have homepage are excluded.
     *
     * @return Collection|Model[]
     */
    public function findForUser(
        User $user,
        $get_subjects = true,
        $access_level = UserAccess::ACCESS_READ,
        $exactAccess = false,
        $requireHomepage = false
    ) {
        $statement = $this->findForUserQuery($user, $get_subjects, $access_level, $exactAccess);

        /** @var Code\Mapper $folderCodeMapper */
        $folderCodeMapper = $this->getJoinMapper('codes');

        $collection = $this->createCollection();
        while($row = $statement->fetch(PDO::FETCH_ASSOC)){
            $id = $row['c.id'];

            // First time we've seen folder?
            if (!isset($collection[$id])) {
                $collection[$id] = $this->hydrate($row, 'c');
                $collection[$id]->setCodes($folderCodeMapper->createCollection());
            }

            $folder = $collection[$id];

            // Does this folder have a homepage, and we've not attached it yet?
            $homepageId = $row['h.cid'];
            if(!$folder->getHomepage() && isset($homepageId)){
                /** @var HomepageConfig $homePage */
                $homePage = $this->hydrateJoin($row, 'homepages', 'h');
                $folder->setHomepage($homePage);
            }

            // Does folder have a group, and we haven't attached it yet?
            $groupId = $row['g.cid'];
            if(!$folder->getGroup() && isset($groupId)){
                // if list requires homepage and this group does not have it, skip.
                if ($requireHomepage && (!isset($homepageId) || $homepageId === null)) {
                    $collection->offsetUnset($id);
                    continue;
                }
                /** @var Group $group */
                $group = $this->hydrateJoin($row, 'groups', 'g');
                $folder->setGroup($group);
            }

            // Add code only if it's not empty
            $code = $row['cc.code'];
            if (isset($code)) {
                /** @var FolderCode\Model $codeModel */
                $codeModel = $this->hydrateJoin($row, 'codes', 'cc');
                $collection[$id]->getCodes()[$codeModel->getCode()] = $codeModel;
                $codeModel->setFolder($collection[$id]);
            }
        }
        $collection->reindex();
        return $collection;
    }

    /**
     * @param  \External_UserInterface $external_user
     *
     * @todo Have this be set up automatically if at all possible
     *
     * @return $this
     */
    public function setExternalUser($external_user)
    {
        $this->external_user = $external_user;
        return $this;
    }

    /**
     * @return \External_UserInterface
     */
    public function getExternalUser()
    {
        // JIT-instantiation (is that really the way it should be?)
        if (!$this->external_user instanceof External_UserInterface) {
            $this->external_user = new \External_User();
        }
        return $this->external_user;
    }


    /**
     * Gets a collection of parents for the given category, starting from the
     * topmost parent first.
     *
     * @param  Model $category
     * @param  Model $stop_category If supplied, the parent list is truncated
     *                                 above this category.
     * @param bool $cheaply If true, use the findCheaplyById() for speed
     * @return Collection
     */
    public function findParents(Model $category, Model $stop_category = null, $cheaply = false)
    {
        /** @var Model[] $stack */
        $stack = array($category);
        $count = 0;

        $stop_id = null;
        if ($stop_category instanceof Model) {
            $stop_id = $stop_category->getId();
        }

        // Keep a record of ancestry
        $ancestry = array();
        if ($category->getId()) {
            $ancestry[$category->getId()] = true;
        }

        // Get the parent of the first item in the stack, and add it to the top
        // of the stack.
        while ($count++ < 50) {
            $parent = $stack[0]->getParent();
            if (!($parent instanceof Model)) {
                break;
            }
            $parent_id = $parent->getId();
            if (!$parent_id || $parent_id === IFolder::ROOT_PARENT_ID || (!empty($stop_id) && $stop_id == $parent_id)) {
                break;
            }

            // This category is its own parent somehow. That shouldn't happen.
            if (array_key_exists($parent_id, $ancestry)) {
                throw new \OverflowException(
                    'Folder "'.$category->getId().'" is its own parent somehow'
                );
            }
            $ancestry[$parent_id] = true;

            /* Cheaply or not?
             */
            if ($cheaply) {
                $node = $this->findCheaplyById($parent_id);
            }
            else {
                $node = $this->findById($parent_id);
            }

            array_unshift($stack, $node);
        }

        // Remove the current category from the parent list first.
        array_pop($stack);
        return new Collection($stack);
    }

    /**
     * Recursive delete of a category and its contents
     *
     * @param Model $category
     */
    public function recursiveDelete(Model $category)
    {
        $groupHandler = new \Group();

        if ($groupHandler->getPermission($category->getId()) < UserAccess::ACCESS_WRITE) {
            error(6);
        }

        $allChildren = $this->findByColumn('parent', $category->getId());

        /* Recurse into child folders
         */
        foreach ($allChildren as $currentChild) {
            $this->recursiveDelete($currentChild);
        }

        /* Delete folder
         * This used to be optional, but it was always used in this context
         *
         * This also just marks the category as deleted, and leaves homepage
         * configs, components, groups etc. untouched
         */
        $this->delete($category);
    }


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @see findByParent()
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    public function findByParentId($nodeId)
    {
        return $this->findByParent($nodeId);
    }

    /**
     * Creates a breadcrumb of a category's hierarchical navigation
     * Optionally include the $category as the final crumb
     *
     * @param Model $category
     * @param bool $includeCategory
     * @return Collection
     */
    public function breadcrumb(Model $category, $includeCategory = false)
    {
        $breadcrumb = new Collection();

        if ($includeCategory) {
            $breadcrumb->add($category);
        }

        $parentId = $category->getParent()->getId();

        /* get the breadcrumb back up to the root directory
         */
        $parentFolders = [];
        while ($parentId != 0) {
            try {
                $nextCategory = $this->findCheaplyById($category->getParent()->getId());
                $breadcrumb->prepend($nextCategory);

                $category = $nextCategory;

                $parentId = $nextCategory->getParent()->getId();
                if (isset($parentFolders[$parentId])) {
                    // I'm my own parent - that's just crazy
                    break;
                }
                $parentFolders[$parentId] = true;
            } catch (\Exception $e) {
                // I don't have any parents - abort
                break;
            }
        }

        return $breadcrumb;
    }

    /**
     * A budget version of findById
     *
     * Does not hydrate any joins
     *
     * @param int $folderId TODO Upgrade this type-hint to a type-declaration in signature
     * @return Model
     * @throws DefinitionException
     * @throws Exception
     * @throws Exception\Deleted
     * @throws Exception\NotFound
     * @throws InvalidArgumentException
     */
    public function findCheaplyById($folderId): Model
    {
        // First, check the local body cache to see if we've already loaded this folder
        if ($this->isCacheEnabled() && $this->localBodyCache->contains($folderId)) {
            return $this->localBodyCache->get($folderId);
        }

        // special case: 0 == root, but not in db
        if ($folderId === Model::ROOT_ID || $folderId === (string)Model::ROOT_ID) {
            return $this->findById($folderId);
        }

        // Need to override default 'ORDER BY' clause that assumes default joins
        $options = [
            'deleted' => true,
            'order'   => [],
        ];

        /** @var Model $folder */
        $folder = $this->executeSelect(
            "SELECT {$this->getColumnString()}",
            "FROM {$this->getTable()}",
            $this->getPredicateBuilder(),
            [$this->getIdentifierColumn() => $folderId],
            $options
        )->first();
        if ($folder) {
            if ($folder->isDeleted()) {
                throw (new Exception\Deleted())->setDeletedObject($folder);
            }
            return $folder;
        }
        throw new MapperException\NotFound();
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return Result
     */
    public function search(ArticleResolver $articleResolver, $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($articleResolver, $user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }


    /* Getters/Setters
     */


    /**
     * Delete folder and children
     *
     * @param int $id
     * @param \Schoolbox\User $deletor
     * @throws SchoolboxException
     */
    public function deleteFolder($id, $deletor)
    {
        // Grab handle to mapper as we'll reuse it

        $folderMapper = FolderMapper::getInstance();

        try
        {
            // Find the folder object

            $folder = $folderMapper->findById($id);

            // Make sure user has write permission to the folder

            if (Access::getInstance()->getFolderAccess($deletor, $id) >= UserAccess::ACCESS_WRITE) {

                // If the folder has children, then we will only allow superusers to delete it

                if ($folderMapper->countByColumn('parent', $id))
                {
                    if (!$deletor->isSuperuser())
                    {
                        throw new SchoolboxException('Access Denied');
                    }
                }

                // Deletes all contents of this folder and all of its children.

                $folderMapper->recursiveDelete($folder);
            }
        }
        catch (\Exception $e)
        {
            // Permission denied or something wasn't found, do nothing and just return. The folder won't have
            // been deleted.
        }
    }

    /**
     * Move folder to new parent with permission check
     *
     * @param int $source
     * @param int $destination
     * @param \Schoolbox\User $mover
     * @throws SchoolboxException
     * @throws MapperException
     */
    public function moveFolder($source, $destination, $mover)
    {
        $errorMapper = ErrorMapper::getInstance();

        // Check if there is nothing to do

        // I AM REASONABLY SURE THIS SHOULD NOT JUST EXIT OUT
        if ($source == $destination) {
            return;
        }

        // Grab folder object

        $folderMapper = FolderMapper::getInstance();

        $movedFolder  = $folderMapper->findById($source);
        $parentFolder = $folderMapper->findById($destination);

        // Check folder access

        if (Access::getInstance()->getFolderAccess($mover, $source) < UserAccess::ACCESS_WRITE ||
            Access::getInstance()->getFolderAccess($mover, $destination) < UserAccess::ACCESS_WRITE ||
            Access::getInstance()->getFolderAccess($mover, $movedFolder->getParent()->getId()) < UserAccess::ACCESS_WRITE
        ) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }

        // Make sure the moved folder is not going to be its own ancestor

        foreach ($folderMapper->findParents($parentFolder) as $ancestor) {
            if ($ancestor->getId() == $movedFolder->getId()) {
                /** @var SchoolboxException $e */
                throw $errorMapper->generateException(ErrorMapper::ERROR_DATABASE, $e);
            }
        }

        $folderMapper->save($movedFolder->setParent($parentFolder));
    }

    /**
     * Return a list of folders that the student has submitted work to in a given term
     *
     * Folder is hydrated with:
     *  * folder codes
     *
     * @param User $student
     * @param null|Term $term
     * @return Collection|Model[]
     * @throws DefinitionException
     */
    public function findFoldersWithResponses(User $student, Term $term = null)
    {
        /** @var Code\Mapper $codeMapper */
        $codeMapper           = $this->getJoinMapper('codes');

        $whereParams = [
            ':studentId' => $student->getId(),
        ];

        // Are we limiting assessments to those due in some term ?
        $termWhere = '';
        if($term instanceof Term){
            $whereParams += $this->buildWhereParamsForTerm($term);
            $termWhere = $this->buildWherePartForTerm($term);
        }


        $query = "
            SELECT
                {$this->getColumnString('f')},
                {$codeMapper->getColumnString('fc')}
            FROM {$this->getTable()} f
            LEFT JOIN {$this->getJoinSql('codes', 'f', 'fc')}
            WHERE f.deleted_at IS NULL AND f.id IN
            ( 
              SELECT DISTINCT COALESCE(`af`.`folder_id`, `ci`.`folder_id`) AS `folder_id`
              FROM `assessment` AS `a`
              LEFT JOIN `assessment_folder`                     AS `af`   ON `a`.`id`        = `af`.`assessment_id`
              LEFT JOIN `assessment_course_unit_implementation` AS `acui` ON `a`.`id`        = `acui`.`assessment_id`
              LEFT JOIN `course_unit_implementation`            AS `cui`  ON `cui`.`id`      = `acui`.`course_unit_implementation_id`  AND `cui`.deleted_at IS NULL 
              LEFT JOIN `course_implementation`                 AS `ci`   ON `ci`.`id`       = `cui`.`course_implementation_id` AND `ci`.deleted_at IS NULL 
              LEFT JOIN `task`                                  AS `t`    ON `a`.`id`        = `t`.`assessment_id` AND `t`.deleted_at IS NULL 
              LEFT JOIN `assess`                                AS `q`    ON `a`.`id`        = `q`.`assessment_id` AND `q`.deleted_at IS NULL 
              LEFT JOIN `submission_box`                        AS `d`    ON `a`.`id`        = `d`.`assessment_id`
              LEFT JOIN `task_completion`                       AS `tc`   ON `t`.`id`        = `tc`.`task_id` AND `tc`.deleted_at IS NULL 
              LEFT JOIN `assess_attempt`                        AS `qa`   ON `q`.`assess_id` = `qa`.`assess_id` AND `qa`.deleted_at IS NULL 
              LEFT JOIN `student_submission`                    AS `ss`   ON `ss`.`box_id`   = `d`.id AND `ss`.deleted_at IS NULL 
              WHERE 
                (`tc`.`student_id` = :studentId OR `ss`.`owner` = :studentId OR `qa`.`user_id` = :studentId)
                {$termWhere}
                AND `a`.deleted_at IS NULL
            )
            ORDER BY f.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($whereParams);

        /** @var GenericCollection|Model[] $folders Indexed by id */
        $folders = $this->createCollection();

        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $folderId = $row["f.{$this->getIdentifierColumn()}"];

            if ($folders[$folderId] == null) {
                $folder = $this->hydrate($row, 'f');
                $folders[$folderId] = $folder;
                $folder->setCodes($codeMapper->createCollection());
            } else {
                $folder = $folders[$folderId];
            }

            // We need to index by code to de-dupe if mapper hydrates this folder somewhere else
            $codeCollection = $folder->getCodes();
            $code = $row['fc.code'];
            if($code && !$codeCollection->offsetExists($code)){
                /** @var FolderCode\Model $folderCode */
                $folderCode = $this->hydrateJoin($row, 'codes', 'fc');
                $codeCollection->setOffset($code, $folderCode);
                $folderCode->setFolder($folder);
            }
        }
        return $folders;
    }


    /**
     * Return a set of folders where the student has received feedback
     *
     * Hydrated joins:
     * * folder code
     *
     * @param User $student
     * @param Term|null $term
     * @return GenericCollection
     * @throws DefinitionException
     */
    public function findFoldersWithFeedbackToStudent(User $student, Term $term = null) {

        $whereClause = "(`tkc`.`student_id` = :userId OR `dwr`.`owner` = :userId OR `qza`.`user_id` = :userId)";
        $whereParams = [
            ':userId' => $student->getId(),
        ];

        if ($term instanceof Term) {
            $whereClause .= $this->buildWherePartForTerm($term);
            $whereParams += $this->buildWhereParamsForTerm($term);
        }

        return $this->findFoldersWithFeedbacks($whereClause, $whereParams);
    }

    /**
     * Return a set of folders where the teacher has provided feedback in the specified term
     *
     * Hydrated joins:
     *  * folder codes
     *
     * Includes:
     * 1. assessments directly in folders
     * 2. assessments in UnitImpls on class folders
     * 3. assessments in Project directly on Folders
     * 4. assessments in Projects in UnitImpls on class Folders
     *
     * @param User $teacher
     * @param Term|null $term
     * @return GenericCollection|Model[]
     */
    public function findFoldersWithFeedbacksFromTeacher(User $teacher, Term $term = null)
    {

        $whereClause = "(`tkc`.`marking_user_id` = :userId OR `dwr`.`marker` = :userId OR `qza`.`reviewer_id` = :userId)";
        $whereParams = [
            ':userId' => $teacher->getId(),
        ];

        if ($term instanceof Term) {
            $whereClause .= $this->buildWherePartForTerm($term);
            $whereParams += $this->buildWhereParamsForTerm($term);
        }

        return $this->findFoldersWithFeedbacks($whereClause, $whereParams);
    }


    /**
     * Hydrates folders with feedbacks based on a where clause provided
     *
     * Hydrated joins:
     *  * folder code
     *
     * @param $whereClause
     * @param $whereParams
     * @return GenericCollection
     * @throws DefinitionException
     */
    private function findFoldersWithFeedbacks($whereClause, $whereParams)
    {
        $codeMapper = $this->getJoinMapper('codes');

        $query = "
            SELECT
                {$this->getColumnString('f')},
                {$codeMapper->getColumnString('fc')}
            FROM {$this->getTable()} f
            LEFT JOIN {$this->getJoinSql('codes', 'f', 'fc')}
            WHERE f.deleted_at IS NULL AND f.id IN
            (
              SELECT DISTINCT COALESCE(`af`.`folder_id`, `ci`.`folder_id`, `apf`.`folder_id`, `pci`.`folder_id`) AS `folder_id`
              FROM      `assessment`                            AS `a`

              LEFT JOIN `assessment_folder`                     AS `af`   ON `af`.`assessment_id` = `a`.`id`

              LEFT JOIN `assessment_course_unit_implementation` AS `aui`  ON `a`.`id` = `aui`.`assessment_id`
              LEFT JOIN `course_unit_implementation`            AS `ui`   ON `ui`.`id` = `aui`.`course_unit_implementation_id` AND `ui`.deleted_at IS NULL 
              LEFT JOIN `course_implementation`                 AS `ci`   ON `ci`.`id` = `ui`.`course_implementation_id` AND `ci`.deleted_at IS NULL 

              LEFT JOIN `project_assessment`                    AS `pa`   ON `pa`.`assessment_id` = `a`.`id`
              LEFT JOIN `project`                               AS `p`    ON `p`.`id` = `pa`.`project_id` AND `p`.deleted_at IS NULL 
              LEFT JOIN `assessment`                            AS `ap`   ON `ap`.`id` = `p`.`assessment_id` AND `ap`.deleted_at IS NULL 
              LEFT JOIN `assessment_folder`                     AS `apf`  ON `apf`.`assessment_id` = `ap`.`id`

              LEFT JOIN `project_assessment`                    AS `uipa` ON `uipa`.`assessment_id` = `a`.`id`
              LEFT JOIN `project`                               AS `uip`  ON `uip`.`id` = `uipa`.`project_id` AND `uip`.deleted_at IS NULL 
              LEFT JOIN `assessment`                            AS `uiap` ON `uiap`.`id` = `uip`.`assessment_id` AND `uiap`.deleted_at IS NULL 
              LEFT JOIN `assessment_course_unit_implementation` AS `paui` ON `paui`.`assessment_id` = `uiap`.`id`
              LEFT JOIN `course_unit_implementation`            AS `pui`  ON `pui`.`id` = `paui`.`course_unit_implementation_id` AND `pui`.deleted_at IS NULL 
              LEFT JOIN `course_implementation`                 AS `pci`  ON `pci`.`id` = `pui`.`course_implementation_id` AND `pci`.deleted_at IS NULL 

              LEFT JOIN `task`                                  AS `tk`   ON `tk`.`assessment_id` = `a`.`id` AND `tk`.deleted_at IS NULL 
              LEFT JOIN `assess`                                AS `qz`   ON `qz`.`assessment_id` = `a`.`id` AND `qz`.deleted_at IS NULL 
              LEFT JOIN `submission_box`                        AS `dw`   ON `dw`.`assessment_id` = `a`.`id`

              LEFT JOIN `task_completion`                       AS `tkc`  ON `tkc`.`task_id` = `tk`.`id` AND `tkc`.deleted_at IS NULL 
              LEFT JOIN `assess_attempt`                        AS `qza`  ON `qza`.`assess_id` = `qz`.`assess_id` AND `qza`.deleted_at IS NULL 
              LEFT JOIN `submission_return`                     AS `dwr`  ON `dwr`.`box_id` = `dw`.id AND `dwr`.deleted_at IS NULL 
              WHERE $whereClause AND `a`.deleted_At IS NULL
            )
            ORDER BY f.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($whereParams);

        // Maintain separate cache so we can preserve query result ordering
        /** @var Model[] $folderCache - Indexed by folder.id */
        $folderCache = [];
        $folders     = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            $folderId = $row["f.{$this->getIdentifierColumn()}"];

            if (!$folderCache[$folderId] instanceof Model) {
                $folderCache[$folderId] = $this->hydrate($row, 'f');
                $folderCache[$folderId]->setCodes($codeMapper->createCollection());
                $folders->add($folderCache[$folderId]);
            }

            $folder = $folderCache[$folderId];

            // We need to index by code to de-dupe if mapper hydrates this folder somewhere else
            $codeCollection = $folder->getCodes();
            $code           = $row['fc.code'];
            if ($code && !$codeCollection->offsetExists($code)) {
                /** @var FolderCode\Model $folderCode */
                $folderCode            = $this->hydrateJoin($row, 'codes', 'fc');
                $codeCollection[$code] = $folderCode;
                $folderCode->setFolder($folder);
            }
        }
        return $folders;
    }


    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        // By default, order by folder.name, yearLevel.sequence
        return ['name' => 'ASC', 'yl.' .  YearLevel\Mapper::COL_SEQUENCE => 'ASC'];
    }

    /**
     * Builds a where part for a term.
     *
     * TODO replace with predicate builder
     *
     * @param Term $term
     * @return string
     */
    private function buildWherePartForTerm(Term $term)
    {
        if ($term->getActualFinish()->isNull()) {
            return "\nAND (`a`.`due_date` >= :startDate)";
        } else {
            return "\nAND (`a`.`due_date` BETWEEN :startDate AND :endDate)";
        }
    }

    /**
     * Builds where params for a term.
     *
     * TODO replace with predicate builder
     *
     * @param Term $term
     * @return array
     */
    private function buildWhereParamsForTerm(Term $term)
    {
        $whereParams = [
            ':startDate' => $term->getStart(),
        ];
        if (!$term->getActualFinish()->isNull()) {
            $whereParams[':endDate'] = $term->getActualFinish();
        }
        return $whereParams;
    }


    /**
     * Return whether we should fetch classes from external SIS
     *
     * @param bool $getSubjects
     * @param User $user
     * @param int  $accessLevel
     * @param bool $exactAccess
     * @return bool
     */
    private function fetchClassesFromExternalSis(bool $getSubjects, User $user, int $accessLevel, bool $exactAccess): bool
    {
        if (!$getSubjects) {
            // Don't get subjects if caller specifically specified that
            return false;
        }

        $role = $user->getRole();

        if ($role->isStaff()) {
            return true;
            // TODO, the next line is the correct business logic, but may break functionality that relies on this bug. See SA-7364
//            return $accessLevel === UserAccess::ACCESS_WRITE || ($accessLevel < UserAccess::ACCESS_WRITE && !$exactAccess);
        }

        if ($role->isStudent()) {
            // Fetch classes if caller specified READ access, or less that READ but not exact
            return $accessLevel === UserAccess::ACCESS_READ || ($accessLevel < UserAccess::ACCESS_READ && !$exactAccess);
        }

        return false;
    }
}
