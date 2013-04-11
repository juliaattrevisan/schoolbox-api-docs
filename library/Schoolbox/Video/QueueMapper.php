<?php
/**
 * During video transcoding, videos are loaded into the queue then processed in the background.
 * This library enables interaction with the queue, to load and process videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;
use Schoolbox\Mapper;


class QueueMapper extends Mapper {

    /* Possible video states */
    const STATUS_PENDING='pending';
    const STATUS_PROCESSING='processing';
    const STATUS_COMPLETED='completed';
	const STATUS_FAILED='failed';

    /* Possible video types */
    const FILE_TYPE_SBFS = 'sbfs';
    const FILE_TYPE_STORAGE = 'storage';

    protected function mapping () {
        $this->hasTable('video_queue');
        $this->hasModel('\\Schoolbox\\Video\\Queue');
        $this->hasMap(array(
                'column'     => 'video_queue_id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            ));
        $this->hasMap(array(
                'column'     => 'file_id',
                'property'   => 'fileID',
                'type'       => self::TYPE_INTEGER,
            ));
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
				'column'     => 'message',
				'property'   => 'message',
				'type'       => self::TYPE_STRING,
			));
	    $this->hasMap(array(
	             'column'     => 'duration',
	             'property'   => 'duration',
	             'type'       => self::TYPE_INTEGER,
	        ));
        $this->hasMap(array(
                'column'            => 'createdOn',
                'property'          => 'created',
                'type'              => self::TYPE_DATETIME,
                'created_timestamp' => true,
            ));
        $this->hasMap(array(
                'column'            => 'modifiedOn',
                'property'          => 'modified',
                'type'              => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            ));
    }

    public function getPending() {
        return $this->findByColumn('status', self::STATUS_PENDING);
    }

}
