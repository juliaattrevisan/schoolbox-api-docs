<?php
/**
 * This model represents a video storage in OTFS and its associated thumbnail/poster (if they've been generated)
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   2017 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use PDO;
use PDOStatement;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 * Class Mapper
 *
 * @package Schoolbox\Video
 */
class Mapper extends BaseMapper
{
    // Possible video preview sizes

    const TYPE_VIDEO     = 'video';
    const TYPE_POSTER    = 'poster';
    const TYPE_THUMBNAIL = 'thumbnail';

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('video');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('video_hash', StorageMapper::class, 'hash', null, null, 'video', 'video');
        $this->joins('poster_hash', StorageMapper::class, 'hash', null, null, 'poster', 'poster');
        $this->joins('thumbnail_hash', StorageMapper::class, 'hash', null, null, 'thumbnail', 'thumbnail');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        $videoMapper     = $this->getJoinMapper('video');
        $posterMapper    = $this->getJoinMapper('poster');
        $thumbnailMapper = $this->getJoinMapper('thumbnail');

        $query = "
            SELECT      {$this->getColumnString()},
                        {$videoMapper->getColumnString('v')},
                        {$posterMapper->getColumnString('p')},
                        {$thumbnailMapper->getColumnString('t')},
				        {$videoMapper->getJoinColumnString('allowedFileExtension', 'vm')},
				        {$posterMapper->getJoinColumnString('allowedFileExtension', 'pm')},
				        {$thumbnailMapper->getJoinColumnString('allowedFileExtension', 'tm')}
            FROM        {$this->database->quoteIdentifier($this->table)}
			JOIN        {$this->getJoinSql('video', '', 'v')}
			LEFT JOIN   {$this->getJoinSql('poster', '', 'p')}
			LEFT JOIN   {$this->getJoinSql('thumbnail', '', 't')}
			LEFT JOIN   {$videoMapper->getJoinSql('allowedFileExtension', 'v', 'vm', true)}
			LEFT JOIN   {$posterMapper->getJoinSql('allowedFileExtension', 'p', 'pm', true)}
			LEFT JOIN   {$thumbnailMapper->getJoinSql('allowedFileExtension', 't', 'tm', true)}
        ";

        return $query;
    }

    /**
     * @param PDOStatement $statement
     *
     * @return GenericCollection
     * @throws MapperException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $hydratedCollection = $this->createCollection();

        // Shut the front door there are a goddamn lot of hoops to jump through to get a mime type!
        //
        //        ▄████████████▄▐█▄▄▄▄█▌
        //        █████▌▄▌▄▐▐▌██▌▀▀██▀▀
        //        ███▄█▌▄▌▄▐▐▌▀██▄▄█▌
        //        ▄▄▄▄█████████████

        /** @var StorageMapper $storageMapper */
        $videoMapper = $this->getJoinMapper('video');

        /** @var StorageMapper $posterMapper */
        $posterMapper = $this->getJoinMapper('poster');

        /** @var StorageMapper $thumbnailMapper */
        $thumbnailMapper = $this->getJoinMapper('thumbnail');

        /** @var MimetypeMapper $videoMimeMapper */
        $videoMimeMapper = $videoMapper->getJoinMapper('allowedFileExtension');

        /** @var MimetypeMapper $posterMimeMapper */
        $posterMimeMapper = $posterMapper->getJoinMapper('allowedFileExtension');

        /** @var MimetypeMapper $thumbnailMimeMapper */
        $thumbnailMimeMapper = $thumbnailMapper->getJoinMapper('allowedFileExtension');

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @var Model $record */
            $record = $this->hydrate($row);

            // Hydrate video

            if (!empty($row['video_hash'])) {
                /** @var Storage $video */
                $video = $this->hydrateJoin($row, 'video', 'v');

                // Hydrate a mime type if we can find one

                /** @var Mimetype $mime */
                $mime = $videoMimeMapper->hydrate($row, 'vm');

                if ($mime instanceof Mimetype) {
                    $video->setMimetype($mime);
                }

                $record->setVideo($video);
            }

            // Hydrate poster

            if (!empty($row['poster_hash'])) {
                /** @var Storage $poster */
                $poster = $this->hydrateJoin($row, 'poster', 'p');

                // Hydrate a mime type if we can find one

                /** @var Mimetype $mime */
                $mime = $posterMimeMapper->hydrate($row, 'pm');

                if ($mime instanceof Mimetype) {
                    $poster->setMimetype($mime);
                }

                $record->setPoster($poster);
            }

            // Hydrate thumbnail

            if (!empty($row['thumbnail_hash'])) {
                /** @var Storage $thumbnail */
                $thumbnail = $this->hydrateJoin($row, 'thumbnail', 't');

                // Hydrate a mime type if we can find one

                /** @var Mimetype $mime */
                $mime = $thumbnailMimeMapper->hydrate($row, 'tm');

                if ($mime instanceof Mimetype) {
                    $thumbnail->setMimetype($mime);
                }

                $record->setThumbnail($thumbnail);
            }

            $hydratedCollection->add($record);
        }

        return $hydratedCollection;
    }
}
