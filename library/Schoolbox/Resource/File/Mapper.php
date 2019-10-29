<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\IHomepageCleanable;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use PDO;
use PDOStatement;


/**
 * Class Mapper
 *
 * File Mapper. Maps file table to SBFS file objects.
 *
 * @package Schoolbox\Resource\File
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($id)
 * @method Model createModel($idValue = null)
 */
class Mapper extends Resource\AbstractFileMapper implements ILocator, IFileMapper, Searchable, IHomepageCleanable
{

    /**
     * @return string
     */
    protected function getFolderFieldname()
    {
        return 'category';
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasTable('file');
        $this->hasModel(Model::class);

        $this->uses('comment', self::TYPE_STRING, null, 'description'); // TODO rename that field!
        $this->uses('hidden', self::TYPE_BOOLEAN);
        $this->uses('modified', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'modifiedAt');
        $this->joins('modified_by', UserMapper::class, 'id');
        $this->joins('category', Resource\Folder\Mapper::class, 'id', null, null, 'folder', 'category');

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'ensureOwnerIsNotNull']);
    }

    /**
     * Port of handler method `File->getAllInCategory()`.
     *
     * @param array  $folderIds
     * @param array  $options
     *               * name: an exact filename to show
     *               * size: number of items to show
     *               * filter: list of filetypes to show (all, audio, video, image, document)
     *               * sort: field to sort on (name, date, random)
     *               * sortdir: direction to sort on (asc, desc)
     *               * getHidden: get hidden files too (default to false)
     *
     * @return Collection|Model[]
     */
    public function findForFolders($folderIds, array $options = [])
    {
        $name          = isset($options['name']) ? $options['name'] : '';
        $limit         = !empty($options['size']) ? intval($options['size']) : 0;
        $getHidden     = !empty($options['getHidden']);
        $fileTypes     = !empty($options['filter']) ? $options['filter'] : array();
        $sortColumn    = !empty($options['sort']) ? $options['sort'] : 'name';
        $sortDirection = !empty($options['sortdir']) ? $options['sortdir'] : 'asc';
        if (empty($folderIds)) {
            return new Collection();
        }
        // much join many wow
        $table     = $this->getTable();
        $baseQuery = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('hash', 'h')},
                {$this->getJoinColumnString('hash.allowedFileExtension', 'm')},
                {$this->getJoinColumnString('owner', 'u')},
                {$this->getJoinColumnString('modified_by', 'um')},
                {$this->getJoinColumnString($this->getFolderFieldname(), 'c')}
            FROM {$table}
            LEFT JOIN {$this->getJoinSql('hash', $table, 'h')}
            LEFT JOIN {$this->getJoinSql('hash.allowedFileExtension', 'h', 'm', true)}
            LEFT JOIN {$this->getJoinSql('owner', $table, 'u', true)}
            LEFT JOIN {$this->getJoinSql('modified_by', $table, 'um', true)}
            LEFT JOIN {$this->getJoinSql($this->getFolderFieldname(), $table, 'c')}
        ";

        // Slam together a queery
        $whereParts  = [
            "category IN {$this->getArrayPlaceholders($folderIds, 'folder')}"
        ];
        $whereParams = $this->getArrayParameters($folderIds, 'folder');
        if (!$getHidden) {
            $whereParts[] = "$table.hidden IS FALSE";
        }

        if (strlen($name)) {
            $whereParts[]            = "$table.description = :filename";
            $whereParams['filename'] = $name;
        }

        // Put together mimetype filters
        $fileTypeMimes = [
            'document' => [
                "%pdf%",
                "%msword%",
                "%vnd.ms%",
                "%rtf%",
                "%text%",
                "%document%",
                "%powerpoint%",
            ],
            'image' => ["%image%",],
            'audio' => ["%audio%",],
            'video' => ["%video%", "%flash%", "%realmedia%",],
        ];

        if (!is_array($fileTypes)) {
            $fileTypes = [$fileTypes];
        }
        if (!in_array('all', $fileTypes)) {
            $mimeParts      = [];
            $otherMimeParts = [];
            foreach ($fileTypes as $fileType) {
                if (!empty($fileTypeMimes[$fileType])) {
                    foreach ($fileTypeMimes[$fileType] as $mime) {
                        $mimeParts[] = "m.mime LIKE '$mime'";
                    }
                } elseif ($fileType == 'other') {
                    // Other: any not in document, image, audio or video
                    foreach ($fileTypeMimes as $fileType => $mimesForFileType) {
                        foreach ($mimesForFileType as $mime) {
                            $otherMimeParts[] = "m.mime LIKE '$mime'";
                        }
                    }
                    //Return all files regardless their extension
                    $mimeParts[] = "h.ext = '' OR m.fileTypeId IS NULL";
                }
            }
            if (!empty($otherMimeParts)) {
                $mimeParts[] = 'NOT('.implode(' OR ', $otherMimeParts).')';
            }
            if (!empty($mimeParts)) {
                $whereParts[] = implode(' OR ', $mimeParts);
            }
        }
        $baseQuery .= ' WHERE '.$table.'.deleted_at IS NULL AND ('.implode(') AND (', $whereParts).')';

        // Put together sort parts
        $sortFields = array(
            'name'   => "$table.description",
            'date'   => "$table.submitted",
            'random' => "RAND()",
        );
        $sortColumn    = isset($sortFields[$sortColumn]) ? $sortColumn : 'name';
        $sortDirection = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';

        // sorting by name will be handled later to support natural sorting
        if ($sortColumn !== 'name') {
            $baseQuery .= " ORDER BY " . $sortFields[$sortColumn] . " " . $sortDirection;
        }

        if ($limit > 0) {
            $baseQuery .= " LIMIT 0, :limit";
        }

        $statement = $this->getDatabase()->prepare($baseQuery);
        foreach ($whereParams as $param => $value) {
            $statement->bindValue($param, $value);
        }
        if ($limit > 0) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }
        $statement->execute();
        $files = $this->hydrateSelect($statement);

        // sort the array by title using natural sort
        if ($sortColumn === 'name') {
            if ($sortDirection === 'DESC') {
                $callback = function ($a, $b) {
                    return strnatcasecmp($b->getTitle(), $a->getTitle());
                };
            } else {
                $callback = function ($a, $b) {
                    return strnatcasecmp($a->getTitle(), $b->getTitle());
                };
            }
            $files = $files->sortByCallback($callback, true);
        }

        return $files;
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return Resource\File\Search\Result
     */
    public function search(ArticleResolver $articleResolver, $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Resource\File\Search\Result();
        $searcher->search($articleResolver, $user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }

    /**
     * Pre-save callback: ensures that owner is not null.
     *
     * @param  Model $model
     * @return bool  Always true
     */
    public function ensureOwnerIsNotNull(Model $model)
    {
        if ($model->getOwner() instanceof User) {
            if (!$model->getOwner()->getId()) {
                $dummyUser = new User();
                $dummyUser->setId(0);
                $model->setOwner($dummyUser);
            }
        }
        return true;
    }

    /**
     * Soft-deletes content for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid){
        $this->deleteByColumn('category',$cid);
    }
}
