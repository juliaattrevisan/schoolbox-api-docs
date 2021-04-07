<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Assessment\DueWork\Decorator;

use Aura\Di\Exception\ServiceNotFound;
use DomainException;
use InvalidArgumentException;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AttachedFilesTrait;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use UnexpectedValueException;

/**
 * Class Decorator
 *
 * Decorate an IAssessable instance with Due Work-specific fields
 * but ignore all the fields that are Course Managed so even if you subverted
 * the ui, you still can't mutate these fields
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Decorator
 */
class CourseManagedDecorator implements DecoratorInterface
{
    use DueWorkTrait;
    use AttachedFilesTrait;

    /** @var DueWork|null $original */
    private $original;

    /**
     * Decorator constructor.
     *
     * @param int          $acceptOnline
     * @param int          $markType
     * @param int          $gradingScheme
     * @param bool         $useRubric
     * @param int          $capabilityMarkType
     * @param int|null     $capabilityGradeGroup
     * @param bool         $useMarkRange
     * @param float|null   $maxMark
     * @param array|null   $rubricData
     * @param array        $attachedFiles
     * @param string|null  $selfReviewable
     * @param bool         $similarityCheckEnabled
     * @param DueWork|null $original
     */
    public function __construct(
        int $acceptOnline,
        int $markType,
        int $gradingScheme,
        bool $useRubric,
        int $capabilityMarkType,
        ?int $capabilityGradeGroup,
        bool $useMarkRange,
        ?float $maxMark,
        ?array $rubricData,
        array $attachedFiles,
        ?string $selfReviewable,
        bool $similarityCheckEnabled,
        ?DueWork $original
    ) {
        $this->setAcceptOnline(trim($acceptOnline));
        $this->setMarkType(trim($markType));
        $this->setGradingScheme(trim($gradingScheme));
        $this->setUseRubric(trim($useRubric));
        $this->setCapabilityMarkType(trim($capabilityMarkType));
        $this->setCapabilityGradeGroup($capabilityGradeGroup);
        $this->setUseMarkRange(trim($useMarkRange));
        $this->setMaxMark($maxMark);
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);
        $this->setSelfReviewable($selfReviewable);
        $this->setSimilarityCheckEnabled($similarityCheckEnabled);
        $this->original = $original;
    }

    /**
     * @param DueWork $assessment
     * @throws ServiceNotFound
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws RangeException
     * @throws Exception
     * @throws UnexpectedValueException
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

        if ($this->getSelfReviewable() !== null) {
            // Something has come through from post, set to that
            $assessment->setSelfReviewability($this->getSelfReviewable());
        } elseif ($assessment->getSelfReviewability() === null) {
            // $assessment doesn't have an existing setting - set to default
            $assessment->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);
        } // Otherwise let the existing setting persist

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
                        ->setBasename($file->getBasename())
                );
            }
        }

        $assessment->setAttachedFiles($newList);

        /* Similarity Check
         */
        $assessment->setSimilarityCheckEnabled($this->getSimilarityCheckEnabled());
    }
}
