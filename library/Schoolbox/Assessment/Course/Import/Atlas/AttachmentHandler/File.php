<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;

use GuzzleHttp\Client as HttpClient;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Handler for importation of files from Atlas to Schoolbox.
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
    public function attach(Folder $folder, Attachment $attachment, bool $private): void
    {
        // This needs to download the file from a private Atlas instance.
        // I assume I can do this with the same auth token as the API has?
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
            ->setHidden($private);
        $this->fileMapper->save($fileModel);
    }
}
