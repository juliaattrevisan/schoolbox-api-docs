<?php

/**
 * FeedbackFileDecorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Model as DueWorkFeedbackFile;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;

class FileDecorator
{

    /**
     * @var IFeedback
     */
    private $feedback;

    /**
     * An array of $_POST files which can be used to spawn
     * actual Feedback File instances in $assessment
     *
     * @var array
     */
    private $files = [];

    /**
     * FeedbackFileDecorator constructor.
     *
     * @param IFeedback $feedback
     * @param array|null $postFiles
     */
    public function __construct(IFeedback $feedback, ?array $postFiles)
    {
        $this->feedback = $feedback;
        if ($postFiles) {
            $this->files = $postFiles;
        }
    }

    /**
     * Manufacture attached FeedbackFiles to $assessment, where appropriate
     * So far it's just Due Work, but this makes it trivial to add files
     * from an attachzone to an assessment for other types
     */
    public function decorate(): void
    {
        if ($this->feedback instanceof DueWorkFeedback) {
            $factory = new CollectorFactory();
            // Replace files with what was actually submitted
            // (Only if something was actually submitted)
            $this->feedback->setAllFiles(new Collection());
            if (!empty($this->files)) {
                // And repopulate
                foreach ($factory->build($this->files)->getAll() as $attachment) {
                    $this->feedback->getAllFiles()->add(
                        (new DueWorkFeedbackFile())
                            ->setStorage($attachment)
                            ->setFilename($attachment->getFilename())
                    );
                }
            }
        }
    }
}
