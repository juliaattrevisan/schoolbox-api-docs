<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\FileMapper;

class ResourceVideoMapper extends Mapper {

	protected function mapping () {

		parent::mapping();

		$this->hasTable('video_queue');
		$this->hasModel('\\Schoolbox\\Video\\ResourceVideo');

		$this->hasMap(array(
			              'column'     => 'type',
			              'property'   => 'type',
			              'type'       => self::TYPE_STRING,
		              ));
		$this->hasMap(array(
			              'column'     => 'status',
			              'property'   => 'status',
			              'type'       => self::TYPE_STRING,
		              ));
		$this->hasMap(array(
			              'column'     => 'duration',
			              'property'   => 'duration',
			              'type'       => self::TYPE_INTEGER,
		              ));

		$this->hasJoin('file', array(
                         'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
                         'this_key'  => 'file_id',
                         'other_key' => 'id',
                     ));

        $this->hasJoin('storage', array(
                'mapper'    => '\\Schoolbox\\FileMapper',
                'this_key'  => 'file_id',
                'other_key' => 'storage_id',
        ));
	}

	public function findByCategory($category, $limit = false) {

		$videoList = new Collection(array());
		$fileMapper = new FileMapper;

		$query = "
            SELECT {$this->getJoinMapper('file')->getColumnString('f')}, {$this->getColumnString('vq')}, {$fileMapper->getJoinMapper('user')->getColumnString('u')}
                    FROM `{$this->getTable()}` vq
                    JOIN {$this->getJoinSQL('file','vq','f')}
                    JOIN {$fileMapper->getJoinSQL('user','f','u')}
            WHERE category = :cat AND vq.status != 'failed' AND f.deleted='0'
            ";

		if($limit != false) {
			$query .= "LIMIT 0,:lim";
		}

		$statement = $this->database->prepare($query);
		$statement->bindParam(':cat',$category);
		if($limit != false) {
			$statement->bindParam(':lim', $limit, \PDO::PARAM_INT);
		}
		$statement->execute();

		foreach($statement as $row) {
			$video = $this->hydrate($row, 'vq');
			$video->setFile($fileMapper->hydrate($row,'f'));
			$video->getFile()->setOwner($fileMapper->getJoinMapper('user')->hydrate($row,'u'));

			$videoList->add($video);
		}
		return $videoList;

	}

	public function findByFileId($id) {
		$fileMapper = new \Schoolbox\Resource\FileMapper;

		$statement = $this->database->prepare( "
            SELECT {$fileMapper->getColumnString('f')}, {$this->getColumnString('vq')}
                     FROM `{$this->getTable()}` vq
                    JOIN {$this->getJoinSQL('file','vq','f')}
            WHERE f.id = ? AND vq.status = 'completed'
            " );

		$statement->execute(array($id));

		$video = null;
		foreach($statement as $row) {
			$video = $this->hydrate($row, 'vq');
			$video->setFile($fileMapper->hydrate($row,'f'));
		}
		if (!($video instanceof ResourceVideo)) {
			throw new \Schoolbox\Mapper\Exception('Instance of object not found');
		}
		return $video;

	}

    public function findByStorageId($id) {
        $storageMapper = new \Schoolbox\FileMapper;

        $statement = $this->database->prepare( "
            SELECT {$storageMapper->getColumnString('s')}, {$this->getColumnString('vq')}
                     FROM `{$this->getTable()}` vq
                    JOIN {$this->getJoinSQL('storage','vq','s')}
            WHERE s.storage_id = ? AND vq.status = 'completed'
            " );

        $statement->execute(array($id));

        $video = null;
        foreach($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($storageMapper->hydrate($row,'s'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }
        return $video;

    }

    /**
     * Like findByStorageId() except it accepts a brownhole hash instead of a
     * brownhole storage ID.
     *
     * @throws \Schoolbox\Mapper\Exception if no video with this hash was found
     * @param  string $hash
     * @return ResourceVideo
     */
    public function findByStorageHash($hash) {

        $storageMapper = $this->getJoinMapper('storage');
        $statement     = $this->database->prepare("
            SELECT {$storageMapper->getColumnString('s')}, {$this->getColumnString('vq')}
            FROM `{$this->getTable()}` vq
            INNER JOIN {$this->getJoinSQL('storage','vq','s')}
            WHERE s.hash = ? AND vq.status = 'completed'
        ");

        $statement->execute(array($hash));

        $video = null;
        if (($row = $statement->fetch())) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($storageMapper->hydrate($row, 's'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }
        return $video;
    }

}
