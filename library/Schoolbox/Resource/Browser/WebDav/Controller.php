<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Browser\WebDav;

use PortfolioWebDAV;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Browser\FullAccessController;

/**
 * Webdav browser controller.
 *
 * The same as the full access controller, but handles file uploads differently.
 *
 * @property PortfolioWebDAV $fileMapper
 * @property PortfolioWebDAV $folderMapper
 */
class Controller extends FullAccessController
{

    /**
     * Overridden because files are uploaded to WebDav differently.
     *
     * @inheritDoc
     */
    public function postFiles($folderId): void
    {
        $factory  = new CollectorFactory();
        $results  = $factory->build($this->request->post->get('attachment'))->getAll();
        $baseFile = [
            'parent'      => $folderId,
            'description' => '',
        ];

        foreach ($results as $file) {
            $this->fileMapper->create(
                $baseFile,
                [
                    'name'     => $file->getBasename(),
                    'resource' => $this->filesystemProxy->readStream($file),
                ]
            );
        }

        // Output something: I guess whatever the contents of the folder are now
        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $this->treeBuilder->buildChildNodes($folderId),
        ]);
    }
}
