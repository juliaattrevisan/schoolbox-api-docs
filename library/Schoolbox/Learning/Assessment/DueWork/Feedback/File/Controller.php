<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback\File;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile;
use Schoolbox\Mapper;

class Controller extends Base
{

    /**
     * @var SingleFile\Mapper
     */
    private $mapper;


    /**
     * @param SingleFile\Mapper $mapper
     */
    public function __construct(SingleFile\Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * @param SingleFile\Model $file
     * @api   /learning/feedback/file/{fileId}/delete
     * @since 17.0
     */
    public function deleteFile(SingleFile\Model $file)
    {
        $code = 400;
        $data = [];
        try {
            if ($this->mapper->delete($file)) {
                $code           = 200;
                $data['status'] = 'OK';
                $data['id']     = $file->getId();
            } else {
                $data['message'] = "Unable to delete file (id: {$file->getId()})";
            }
        } catch (Mapper\Exception $e) {
            $data['message'] = $e->getMessage();
        }

        $this->getResponse()->status->set($code);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }
}
