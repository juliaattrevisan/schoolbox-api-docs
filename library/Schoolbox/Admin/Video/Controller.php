<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Video;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
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
     * @since 165
     */
    public function getIndex()
    {
        // Render this thing out

        $statuses = [
            VideoQueueMapper::STATUS_PROCESSING,
            VideoQueueMapper::STATUS_PENDING,
            VideoQueueMapper::STATUS_FAILED
        ];

        $queueItems = $this->getVideoQueueMapper()->findByStatus($statuses, ['order' => ['createdOn' => 'desc']]);

        $this->response = $this->responseFromTemplate(
            '@adminVideo/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminVideo.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminVideo.getIndex')),
                'queue' => $queueItems,
                'statusOrder' => $statuses
            ],
            'Admin - Video Queue'
        );
    }

    /**
     * Display log for particular video
     *
     * @api   GET /adminv2/video/:id
     * @since 165
     * @param VideoQueue $queue
     */
    public function getLog($queue)
    {
        $this->response = $this->responseFromTemplateUnthemed(
            '@adminVideo/log.twig',
            [
                'log' => $queue,
            ]
        );
    }

    /**
     * Resubmit video to, or remove it from processing queue
     */
    public function postSubmit()
    {
        $post = $this->requestPost;
        $status = 200;
        try {
            /** @var VideoQueue $video */
            $video = $this->getVideoQueueMapper()->findById($post['videoId']);

            if ($post['videoAction'] == 'resubmit') {
                $video->setStatus(VideoQueueMapper::STATUS_PENDING);
                $this->getVideoQueueMapper()->save($video);
                $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminVideo.getIndex'));
            } else if ($post['videoAction'] == 'delete') {
                $this->getVideoQueueMapper()->delete($video);
            }

            $data = [
                'result' => 'success'
            ];
        } catch (MapperException $e) {
            $data = [
                'result' => 'error'
            ];
            $status = 500;
        }

        $this->response = $this->responseAsJson($data)->withStatus($status);
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
