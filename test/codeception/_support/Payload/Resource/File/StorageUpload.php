<?php


namespace Payload\Resource\File;


use Payload\PostTrait;
use Payload\Request;

class StorageUpload extends Request
{
    use PostTrait;

    public function __construct($container_name, $filename, $file_location_in_data)
    {
        $this->unsetHeader('Content-Type');
        $this->setFile($container_name, $filename, codecept_data_dir($file_location_in_data));
    }

    /** @inheritDoc*/
    public function getUrl(): string
    {
        return "/storage/asyncUpload";
    }
}
