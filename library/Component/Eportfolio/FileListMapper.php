<?php
/**
 * Component_Eportfolio_FileListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Component\Eportfolio;
use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;

class FileListMapper extends Schoolbox\Mapper
{

    /* properties for consistent joinage between methods */
    protected $myFileAlias   = 'mf';
    protected $storageAlias  = 'st';
    protected $mimeAlias     = 'mt';

    protected $myFileJoin    = 'files';
    protected $storageJoin   = 'hash';
    protected $mimeJoin      = 'allowedFileExtension';


    /**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_filelist_item');
		$this->hasModel(FileList::class);

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'pageId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'file_id',
			'property'   => 'fileId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasJoin($this->myFileJoin, array(
            'multiple'  => false,
			'mapper'    => Schoolbox\Resource\My\File\Mapper::class,
			'this_key'  => 'file_id',
			'other_key' => 'id',
		));

		$this->hasJoin('pages', array(
            'multiple'  => false,
			'mapper'    => '\\Eportfolio\\PageMapper',
			'this_key'  => 'page_id',
			'other_key' => 'page_id',
		));

		$this->hasJoin('myfile', array(
			'mapper'    => Schoolbox\Resource\My\File\Mapper::class,
			'this_key'  => 'file_id',
			'other_key' => 'id',
		));
	}

	/**
	 * Find all published image files on a particular page
	 *
	 * @param $pageId
	 * @return Collection
	 */
	public function findAllImagesByPage($pageId) {
        // no need to make it any harder than it needs to be
        return $this->findByColumn('page_id', $pageId, [ 'mime' => 'image/*' ]);
	}


	/**
	 * Retrieves a list of recently-published images
	 *
	 * @param $uid
	 * @param $limit
	 * @return Collection
	 */
	public function findRecent($uid, $limit)
    {
        return $this->findByColumnList([
            "{$this->myFileAlias}.owner" => $uid,
        ], [
            'mime'  => 'image/*',
            'limit' => $limit,
            'order' => [
                'created_at' => 'DESC'
            ]
        ]);
	}


    /**
     * Find all the published files that belong to a particular page
     *
     * @param int $pageId
     * @return Collection|FileList[]
     */
    public function findForPage($pageId)
    {
        return $this->findByColumn('page_id', $pageId, [
            'order' => [ 'created_at' => 'DESC' ]
        ]);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        $myFileMapper  = $this->getJoinMapper($this->myFileJoin);
        $storageMapper = $myFileMapper->getJoinMapper($this->storageJoin);
        $mimeMapper    = $storageMapper->getJoinMapper($this->mimeJoin);

        return "
            SELECT
                {$this->getColumnString()},
                {$myFileMapper->getColumnString($this->myFileAlias)},
                {$storageMapper->getColumnString($this->storageAlias)},
                {$mimeMapper->getColumnString($this->mimeAlias)}
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql($this->myFileJoin, $this->table, $this->myFileAlias)}
            LEFT JOIN {$myFileMapper->getJoinSql($this->storageJoin, $this->myFileAlias, $this->storageAlias)}
            LEFT JOIN {$storageMapper->getJoinSql($this->mimeJoin, $this->storageAlias, $this->mimeAlias, true)}
        ";
    }


    /**
     * @param Builder $predicate
     * @param array   $columnList
     * @param array   $options
     * @return array
     */
    protected function buildWherePart(Builder $predicate, $columnList, $options)
    {
        // if the mime is specified, add a LIKE predicate to the query
        if (isset($options['mime'])) {
            $mime = str_replace('*', '%', $options['mime']);
            $predicate->like("{$this->mimeAlias}.mime", $mime);
        }

        return parent::buildWherePart($predicate, $columnList, $options);
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection|FileList[]
     * @throws Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $myFileMapper  = $this->getJoinMapper($this->myFileJoin);
        $storageMapper = $myFileMapper->getJoinMapper($this->storageJoin);
        $mimeMapper    = $storageMapper->getJoinMapper($this->mimeJoin);

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var FileList $fileListItem */
            $fileListItem = $this->hydrate($row);

            /** @var Schoolbox\Resource\My\File\Model $file */
            $file = $myFileMapper->hydrate($row, $this->myFileAlias);

            /** @var Schoolbox\Resource\Storage\Model $storage */
            $storage = $storageMapper->hydrate($row, $this->storageAlias);

            /** @var Schoolbox\File\Mimetype $mime */
            $mime = $mimeMapper->hydrate($row, $this->mimeAlias);

            // aaaaaand rewind...
            if($storage instanceof Schoolbox\Resource\Storage\Model) {
                $storage->setMimetype($mime);
                $file->setStorage($storage);
            }
            $fileListItem->setFile($file);

            /* Put file list item instance into collection
             */
            $collection->add($fileListItem);
        }

        return $collection;
    }

}
