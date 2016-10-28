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
use Schoolbox\Learning\Assessment\DueWork\Type;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Decorator implements DecoratorInterface
{
    use DueWorkTrait;

    /**
     * Decorator constructor.
     *
     * @param $acceptOnline
     * @param $workType
     * @param $markType
     * @param $gradingScheme
     * @param $usesRubric
     * @param $markPerCapability
     * @param $maxMark
     * @param $rubricData
     * @param $attachedFiles
     */
    public function __construct($acceptOnline, $workType, $markType, $gradingScheme, $usesRubric, $markPerCapability, $maxMark, $rubricData, $attachedFiles)
    {
        $this->setAcceptOnline(trim($acceptOnline));
        $this->setWorkType(intval($workType));
        $this->setMarkType(trim($markType));
        $this->setGradingScheme(trim($gradingScheme));
        $this->setUsesRubric(trim($usesRubric));
        $this->setMarkPerCapability(trim($markPerCapability));
        $this->setMaxMark(trim($maxMark));
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);
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

        $assessment->setType((new Type())->setId($this->getWorkType()));

        $assessment->setMaxMark($this->getMaxMark());

        /* TODO: Replace old classes and stuff?
         */
        $markType = new \Schoolbox\Assessment\Component\SubmissionBox\MarkType();
        $markType->setId($this->getMarkType());
        $assessment->setMarkType($markType);

        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $markScheme = $markSchemeFactory->spawnMarkSchemeFor($assessment);

        $assessment->setMarkScheme($markScheme);

        /* TODO: Replace old classes and stuff?
         */
        $gradingGroup = new \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model();
        $gradingGroup->setId($this->getGradingScheme());

        $assessment->setGradeGroup($gradingGroup);
        $assessment->setUseRubric($this->getUsesRubric());
        $assessment->setMarkPerCapability($this->getMarkPerCapability());


        if($this->getUsesRubric()){
            $rubric = new \Schoolbox\Assessment\Component\SubmissionBox\Rubric\Rubric();
            $rubric    = (new \Schoolbox\Assessment\Rubric\Factory($rubric))->buildFromPostData($this->getRubricData(), false);
            $markMax = $rubric->getTotal();
            $assessment->setMaxMark($markMax);
            $assessment->setRubric($rubric);
        }

        /* If there were attachments, accumulate them
         * Otherwise go away
         */
        if (count($this->getAttachedFiles())) {
            // Rebuild attached files list
            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();
            $newList     = new Collection();
            foreach ($attachments as $file) {
                $newList->add(
                    (new DueWorkFile())
                        ->setHash($file->getHash())
                        ->setFilename($file->getBasename())
                );
            }
            $assessment->setAttachedFiles($newList);
        }
    }
}