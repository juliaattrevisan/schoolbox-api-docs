<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Video;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Video\QueueMapper as VideoQueueMapper;
use Schoolbox\Video\Queue as VideoQueue;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Controller for managing failed video transcoding through the admin section.
 * @package Schoolbox\Admin\Video
 */
class Controller extends Base
{
    const PAGE_CURRENT = 'video';

    /**
     * Display tree
     *
     * @api   GET /adminv2/video
     * @level 165
     */
    public function getIndex()
    {
        // Render this thing out

        $statuses = [
            VideoQueueMapper::STATUS_FAILED,
            VideoQueueMapper::STATUS_PROCESSING,
            VideoQueueMapper::STATUS_PENDING
        ];

        $queue = [];

        $queueStatus = $this->getVideoQueueMapper()->findByColumn('status', $statuses);

        /** @var VideoQueue $video */
        foreach ($queueStatus as $video) {
            try {
                $storageObject = $this->getStorageMapper()->findByHash($video->getHash());
                $video->setStorage($storageObject);
            } catch (MapperException $e) {
            }

            if (!isset($queue[$video->getStatus()])) {
                $queue[$video->getStatus()] = [];
            }

            $queue[$video->getStatus()][] = $video;
        }

        $this->getResponse()->content->set([
            'template' => '@adminVideo/index.twig',
            'data' => [
                'pageCurrent' => self::PAGE_CURRENT,
                'queue' => $queue,
                'statusOrder' => $statuses
            ],
            'title' => 'Admin - Video Queue'
        ]);
    }

    /**
     * Resubmit video to, or remove it from processing queue
     */
    public function postSubmit()
    {
        $post = $this->getRequest()->post;


        try {
            /** @var VideoQueue $video */
            $video = $this->getVideoQueueMapper()->findById($post['videoId']);

            if ($post['videoAction'] == 'resubmit') {
                $video->setStatus('pending');
                $this->getVideoQueueMapper()->save($video);
                $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminVideo.getIndex'));
            } else if ($post['videoAction'] == 'delete') {
                $this->getVideoQueueMapper()->delete($video);
            }
        } catch (MapperException $e) {
        }
    }

    /** Here be mappers and stuff */

    /**
     * @return VideoQueueMapper
     */
    public function getVideoQueueMapper()
    {
        return $this->getDi()->get('mapperFactory')->get(VideoQueueMapper::class);
    }

    /**
     * @return StorageMapper
     */
    public function getStorageMapper()
    {
        return $this->getDi()->get('mapperFactory')->get(StorageMapper::class);
    }
}
