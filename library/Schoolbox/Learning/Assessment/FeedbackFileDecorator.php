<?php
/**
 * FeedbackFileDecorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;

class FeedbackFileDecorator
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
        $this->setFiles($postFiles);
    }

    /**
     * Manufacture attached FeedbackFiles to $assessment, where appropriate
     * So far it's just Due Work, but this makes it trivial to add files
     * from an attachzone to an assessment for other types
     */
    public function decorate()
    {
        if ($this->getFeedback() instanceof DueWorkFeedback) {
            $factory = new CollectorFactory();
            if (!empty($this->getFiles())) {

                // Replace files with what was actually submitted
                // (Only if something was actually submitted)
                $this->getFeedback()->setAllFiles(new Collection());

                // And repopulate
                foreach ($factory->build($this->getFiles())->getAll() as $attachment) {

                    /* Feedback Files are never related to a crocodoc
                     * Crocodoc is always related to a SubmissionFile, via its crocodocId
                     *
                     * Any crocodocable file is sneakily appended to the Feedback File
                     * collection via other business logic, it's never created like that
                     * Such representation is only shown in Timeline views - see
                     * Timeline/Full:200-215
                     *
                     * Though, with the advent of being able to save Feedback Files properly
                     * maybe that responsibility should be moved here instead?
                     */
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
    private function getFeedback()
    {
        return $this->feedback;
    }

    /**
     * @param IFeedback $feedback
     * @return $this
     */
    private function setFeedback($feedback)
    {
        $this->feedback = $feedback;
        return $this;
    }

    /**
     * @return array
     */
    private function getFiles()
    {
        return $this->files;
    }

    /**
     * @param array $files
     * @return $this
     */
    private function setFiles($files)
    {
        $this->files = $files;
        return $this;
    }
}