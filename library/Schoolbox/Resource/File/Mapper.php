<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Collection;
use Schoolbox\Resource;
use Schoolbox\Video\ResourceVideoMapper;

/**
 * Class Mapper
 *
 * File Mapper. Maps file table to SBFS file objects.
 *
 * @package Schoolbox\Resource\File
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($id)
 * @method Model createModel()
 */
class Mapper extends Resource\AbstractFileMapper implements ILocator, IFileMapper
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

        $model = new Model();
        $model->setStorage($this->getStorageMapper()->createModel());

        $this->hasTable('file');
        $this->hasPrototype($model);

        $this->uses('comment', self::TYPE_STRING, null, 'description'); // TODO rename that field!
        $this->uses('hidden', self::TYPE_BOOLEAN);
        $this->uses('modified', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'modifiedAt');

        $this->joins($this->getFolderFieldname(), Resource\Folder\Mapper::class, 'id', null, null, 'folder');

//        // defined longwise because $this->joins doesn't support extra
//        $this->hasJoin(
//            'queue',
//            [
//                'mapper'    => ResourceVideoMapper::class,
//                'this_key'  => 'id',
//                'other_key' => 'file_id',
//                'extra'     => ":other_alias.type = 'sbfs'",
//            ]
//        );
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
    public function findForFolders($folderIds, $options = array())
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
                {$this->getJoinColumnString('hash.queue', 'q')},
                {$this->getJoinColumnString('owner', 'u')},
                {$this->getJoinColumnString($this->getFolderFieldname(), 'c')}
            FROM {$table}
            LEFT JOIN {$this->getJoinSql('hash', $table, 'h')}
            LEFT JOIN {$this->getJoinSql('hash.allowedFileExtension', 'h', 'm', true)}
            LEFT JOIN {$this->getJoinSql('hash.queue', 'h', 'q')}
            LEFT JOIN {$this->getJoinSql('owner', $table, 'u')}
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
        if (!in_array('all', $fileTypes)) {
            $mimetypes = [];
            foreach ($fileTypes as $fileType) {
                switch ($fileType) {
                    case 'document':
                        $mimetypes[] = "%pdf%";
                        $mimetypes[] = "%msword%";
                        $mimetypes[] = "%officedoc%";
                        $mimetypes[] = "%rtf";
                        $mimetypes[] = "text%";
                        $mimetypes[] = "%document%";
                        $mimetypes[] = "%powerpoint%";
                        break;
                    case 'image':
                        $mimetypes[] = "%image%";
                        break;
                    case 'audio':
                        $mimetypes[] = "%audio%";
                        break;
                    case 'video':
                        $mimetypes[] = "%video%";
                        $mimetypes[] = "%flash%";
                        $mimetypes[] = "%realmedia%";
                        break;
                    default:
                        break;
                }
            }
            if (!empty($mimetypes)) {
                $whereParts[] = implode(
                    ' OR ',
                    array_map(
                        function($m) { return "m.mime LIKE '$m'"; },
                        $mimetypes
                    )
                );
            }
        }
        $baseQuery .= ' WHERE ('.implode(') AND (', $whereParts).')';

        // Put together sort parts
        $sortFields = array(
            'name'   => "$table.description",
            'date'   => "$table.submitted",
            'random' => "RAND()",
        );

        $sortColumn    = isset($sortFields[$sortColumn]) ? $sortColumn : 'name';
        $sortDirection = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';
        $baseQuery    .= " ORDER BY " . $sortFields[$sortColumn] . " " . $sortDirection;

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
        return $this->hydrateSelect($statement);
    }
}
