<?php
/**
 * Decorator
 *
 * Decorate an IAssessable instance with Due Work-specific fields
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Decorator;

use Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\DecoratorInterface;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use \Schoolbox\Assessment\Rubric\Factory as RubricFactory;
use \Schoolbox\Assessment\Component\SubmissionBox\Rubric\Rubric as DueWorkRubric;

class Decorator implements DecoratorInterface
{
    use DueWorkTrait;

    /**
     * Decorator constructor.
     *
     * @param $acceptOnline
     * @param $markType
     * @param $gradingScheme
     * @param $useRubric
     * @param $markPerCapability
     * @param $maxMark
     * @param $rubricData
     * @param $attachedFiles
     * @param string $selfReviewable
     */
    public function __construct(
        $acceptOnline,
        $markType,
        $gradingScheme,
        $useRubric,
        $markPerCapability,
        $maxMark,
        $rubricData,
        $attachedFiles,
        $selfReviewable
    ) {
        $this->setAcceptOnline(trim($acceptOnline));
        $this->setMarkType(trim($markType));
        $this->setGradingScheme(trim($gradingScheme));
        $this->setUseRubric(trim($useRubric));
        $this->setMarkPerCapability(trim($markPerCapability));
        $this->setMaxMark(trim($maxMark));
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);
        $this->setSelfReviewable($selfReviewable);
    }

    /**
     * @param DueWork $assessment
     */
    public function decorate($assessment)
    {
        if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_NONE) {
            $assessment->setAcceptsNone();
        }
        else if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_SINGLE) {
            $assessment->setAcceptsSingle();
        }
        else if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_MULTI) {
            $assessment->setAcceptsMultiple();
        }

        $assessment->setMaxMark($this->getMaxMark());

        /* TODO: Replace old classes and stuff?
         */
        $gradingGroup = new \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model();
        $gradingGroup->setId($this->getGradingScheme());

        $assessment->setGradeGroup($gradingGroup);

        /* TODO: Replace old classes and stuff?
         */
        $markType = new \Schoolbox\Assessment\Component\SubmissionBox\MarkType();
        $markType->setId($this->getMarkType());
        $assessment->setMarkType($markType);

        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $markScheme = $markSchemeFactory->spawnMarkSchemeFor($assessment);

        $assessment->setMarkScheme($markScheme);

        $assessment->setUseRubric($this->getUseRubric());
        $assessment->setMarkPerCapability($this->getMarkPerCapability());
        $assessment->setSelfReviewable($this->getSelfReviewable());



        if($this->getUseRubric()){
            /** @var DueWorkRubric $rubric */
            $rubric = (new RubricFactory(new DueWorkRubric()))->buildFromPostData($this->getRubricData(), false);
            $assessment->setRubric($rubric);

            $markMax = $rubric->getTotal();
            $assessment->setMaxMark($markMax);
        }

        /* If there were attachments, accumulate them
         * Otherwise set attachedFiles to an empty collection
         */
        $newList = new Collection();

        if (count($this->getAttachedFiles())) {
            // Rebuild attached files list
            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();

            foreach ($attachments as $file) {
                $newList->add(
                    (new DueWorkFile())
                        ->setStorage($file)
                        ->setFilename($file->getBasename())
                );
            }
        }

        $assessment->setAttachedFiles($newList);

    }
}
