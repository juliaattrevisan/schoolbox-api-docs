<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;

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
     * @api   POST /googleFile/{folderId}
     * @since 21.0
     * @param Folder $folder
     * @throws MapperException
     */
    public function postGoogleFile(Folder $folder)
    {
        $docs = $this->request->post->get('docs');
        if (!$docs) {
            return;
        }

        foreach ($docs as $doc) {
            $googleFile = $this->googleFileMapper->createModel();
            $googleFile->setFolderId($folder->getId())
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
