<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{
    /**
     * The google file mapper
     */
    private Mapper $googleFileMapper;

    /**
     * Constructor
     *
     * @param Mapper $googleFileMapper
     */
    public function __construct(
        Mapper $googleFileMapper
    ) {
        $this->googleFileMapper = $googleFileMapper;
    }

    /**
     * Create a google file from POST data.
     *
     * @api   POST /googleFile/
     * @since 21.0
     * @throws MapperException
     */
    public function postGoogleFile()
    {
        $docs = $this->request->post->get('docs');
        if (!$docs) {
            return;
        }

        foreach ($docs as $doc) {
            $googleFile = $this->googleFileMapper->createModel();
            $googleFile->setFolderId((int)$this->request->post->get('fid'))
                       ->setGoogleId($doc['id'])
                       ->setOwnerId($this->getUser()->getId());
            $this->googleFileMapper->save($googleFile);
        }

        $this->getResponse()->status->set(200);
        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                [
                    'success' => true,
                ]
            )
        );
    }
}
