<?php

/**
 * Decorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Decorator;

use \Schoolbox\Learning\Assessment\DecoratorInterface;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Decorator implements DecoratorInterface
{
    use TaskTrait;

    /**
     * Decorator constructor.
     *
     * @param $hasSelfAssessed
     * @param $url
     * @param $attachedFile
     */
    public function __construct($hasSelfAssessed, $url, $attachedFile)
    {
        $this->setHasSelfAssessed($hasSelfAssessed);
        $this->setUrl($url);
        $this->setAttachedFile($attachedFile);
    }

    /**
     * @param Task $assessment
     */
    public function decorate($assessment)
    {
        $assessment->setIsSelfAssessable($this->getHasSelfAssessed());

        // Build URL out of either URL from form or attachment list from form
        // (ripped out of old task save form)
        $url = $this->getUrl();
        if (empty($url)) {
            $attachment = $this->getAttachedFile();
            if (!empty($attachment)) {
                $folderPrefix = \Schoolbox\Resource\Browser\NodeFactory::SBFS . '.folder:';
                if (strpos($attachment, $folderPrefix) === 0) {
                    // It's a homepage
                    $url = '/homepage/' . substr($attachment, strlen($folderPrefix));

                } else {
                    // It's an uploaded file
                    try {
                        $factory = new CollectorFactory();
                        $file    = $factory->build([$attachment])->getFirst();
                        if ($file->getMimetype()->isImage()) {
                            $url = '/storage/image.php?hash=' . $file->getHash();
                        } else {
                            $url = '/storage/fetch.php?hash=' . $file->getHash();
                        }

                    } catch (\InvalidArgumentException $e) {
                        // noop - leave $url blank
                    }
                }
            }
        }
        $assessment->setUrl($url);
    }
}
