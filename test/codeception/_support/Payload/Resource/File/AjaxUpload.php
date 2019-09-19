<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Resource\File;

use Payload;


class AjaxUpload extends Upload
{

    /**
     * AjaxUpload constructor.
     * @param $folder_id
     * @param $container_name
     * @param $filename
     * @param $file_location_in_data
     */
    public function __construct($folder_id, $container_name, $filename, $file_location_in_data)
    {
        $this->unsetHeader('Content-Type');
        $this->setFile($container_name, $filename, codecept_data_dir($file_location_in_data));
        $this->setCategory($folder_id);
        $this->setDescription('fish2');
        $this->setComment('');
        $this->setQueryParam('ajax', 1);
    }

    /** @inheritDoc*/
    public function getUrl(): string
    {
        return '/cms/fileForm.php';
    }
}
