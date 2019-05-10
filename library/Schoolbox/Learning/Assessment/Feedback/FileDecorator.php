<?php
/**
 * FeedbackFileDecorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;

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
     * @param $postFiles
     */
    public function __construct(IFeedback $feedback, $postFiles)
    {
        $this->setFeedback($feedback);
        if ($postFiles) {
            $this->setFiles($postFiles);
        }
    }

    /**
     * Manufacture attached FeedbackFiles to $assessment, where appropriate
     * So far it's just Due Work, but this makes it trivial to add files
     * from an attachzone to an assessment for other types
     */
    public function decorate(): void
    {
        if ($this->getFeedback() instanceof DueWorkFeedback) {
            $factory = new CollectorFactory();
            // Replace files with what was actually submitted
            // (Only if something was actually submitted)
            $this->getFeedback()->setAllFiles(new Collection());
            if (!empty($this->getFiles())) {
                // And repopulate
                foreach ($factory->build($this->getFiles())->getAll() as $attachment) {
                    $this->getFeedback()->getAllFiles()->add(
                        (new DueWorkFeedbackFile())
                            ->setStorage($attachment)
                            ->setFilename($attachment->getFilename())
                    );
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return IFeedback
     */
    private function getFeedback(): IFeedback
    {
        return $this->feedback;
    }

    /**
     * @param IFeedback $feedback
     * @return $this
     */
    private function setFeedback($feedback): self
    {
        $this->feedback = $feedback;
        return $this;
    }

    /**
     * @return array
     */
    private function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param array $files
     * @return $this
     */
    private function setFiles($files): self
    {
        $this->files = $files;
        return $this;
    }
}
