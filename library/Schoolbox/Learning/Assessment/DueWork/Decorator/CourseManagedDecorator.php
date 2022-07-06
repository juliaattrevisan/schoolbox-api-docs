<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Decorator;

use DomainException;
use InvalidArgumentException;
use RangeException;
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
     * @param int          $markType
     * @param int          $gradingScheme
     * @param bool         $useRubric
     * @param int          $capabilityMarkType
     * @param int|null     $capabilityGradeGroup
     * @param bool         $useMarkRange
     * @param float|null   $maxMark
     * @param array|null   $rubricData
     * @param array        $attachedFiles
     * @param DueWork|null $original
     */
    public function __construct(
        int $markType,
        int $gradingScheme,
        bool $useRubric,
        int $capabilityMarkType,
        ?int $capabilityGradeGroup,
        bool $useMarkRange,
        ?float $maxMark,
        ?array $rubricData,
        array $attachedFiles,
        ?DueWork $original
    ) {
        $this->setMarkType($markType);
        $this->setGradingScheme($gradingScheme);
        $this->setUseRubric($useRubric);
        $this->setCapabilityMarkType($capabilityMarkType);
        $this->setCapabilityGradeGroup($capabilityGradeGroup);
        $this->setUseMarkRange($useMarkRange);
        $this->setMaxMark($maxMark);
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);
        $this->original = $original;
    }

    /**
     * @param DueWork $assessment
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws RangeException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function decorate($assessment)
    {
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
    }
}
