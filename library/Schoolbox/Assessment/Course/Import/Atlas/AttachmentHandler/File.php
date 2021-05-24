<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use GuzzleHttp\Client as HttpClient;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;

/**
 * Handler for importation of files from Atlas to Schoolbox.
 *
 * Currently this is not in use, due to being unable to configure a HTTP client
 * to pass Atlas' auth challenge.
 */
class File implements IAttachmentHandler
{
    /**
     * @var HttpClient
     */
    private $httpClient;


    /**
     * @var StorageMapper
     */
    private $storageMapper;


    /**
     * @var FileMapper
     */
    private $fileMapper;


    /**
     * Constructor
     *
     * @param HttpClient    $httpClient
     * @param StorageMapper $storageMapper
     * @param FileMapper    $fileMapper
     */
    public function __construct(HttpClient $httpClient, StorageMapper $storageMapper, FileMapper $fileMapper)
    {
        $this->httpClient    = $httpClient;
        $this->storageMapper = $storageMapper;
        $this->fileMapper    = $fileMapper;
    }


    /**
     * @inheritdoc
     */
    public function attach(Attachment $attachment, Folder $folder, bool $private, User $owner): void
    {
        // TODO somehow configure a HTTP client to pass Atlas' auth challenge
        $download = $this->httpClient->get($attachment->getUrl());

        $storageModel = $this->storageMapper->acceptStream(
            basename($attachment->getUrl()),
            $download->getBody()->detach(),
            false
        );

        $fileModel = $this->fileMapper->createModel()
            ->setFolder($folder)
            ->setTitle($attachment->getTitle())
            ->setStorage($storageModel)
            ->setOwner($owner)
            ->setHidden($private);
        $this->fileMapper->save($fileModel);
    }


    /**
     * Files are enabled and private by default.
     *
     * @param Attachment $attachment
     * @return array
     */
    public function getDefaultSettings(Attachment $attachment): array
    {
        return [
            'enabled' => true,
            'private' => true,
        ];
    }
}
