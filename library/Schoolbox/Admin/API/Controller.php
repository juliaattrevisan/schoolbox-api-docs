<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\API;

use Alaress\Web;
use InvalidArgumentException;


class Controller extends Web\Controller\Base
{
    /**
     * @api POST /api/admin/csv
     */
    public function postUploadCsv()
    {
        $responseJson = [
            'success' => false
        ];

        $rs = $this->getResponse();

        // regrettably we can't do these checks in the module, because we don't have access to the $_POST array

        $key  = $this->getRequest()->post->get('key');

        try {

            if (!$key) {
                throw new InvalidArgumentException('No authentication token was supplied.', 401);
            }

            if ($this->getConfigLoader()->get('api_key') !== $key) {
                throw new InvalidArgumentException('The given authentication token is invalid.', 403);
            }

            // key validated; you may proceed

            $file = $this->getRequest()->files->get('file');

            if (!$file) {
                throw new InvalidArgumentException('No CSV was received by the server.', 400);
            }

            try {
                $storage = \Schoolbox\Resource\Storage\Mapper::getInstance()->accept($file['name'], $file['tmp_name'], false);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new InvalidArgumentException('The CSV was rejected by the server.', 400);
            }

            $responseJson['success'] = true;
            $responseJson['file'] = $storage->getHash();

        } catch (InvalidArgumentException $e) {
            $responseJson['error'] = $e->getMessage();
            $rs->status->setCode($e->getCode());
        }

        $rs->content->set($this->buildJsonOutput($responseJson));
    }


    /**
     * @api POST /api/post/process
     */
    public function postProcess()
    {
    }
}