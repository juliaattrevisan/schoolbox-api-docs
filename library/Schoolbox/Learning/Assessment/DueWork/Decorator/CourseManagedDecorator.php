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
use Schoolbox\Learning\Assessment\DataModel\RubricTrait;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
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
    use RubricTrait;


    /**
     * Decorator constructor.
     *
     * @param int          $markType
     * @param int          $instructorMarkType
     * @param int|null     $gradingScheme
     * @param int|null     $instructorGradingScheme
     * @param float|null   $maxMark
     * @param float|null   $instructorMaxMark
     * @param array|null   $rubricData
     * @param null|array   $attachedFiles
     * @param bool         $enableWorksheet
     * @param string| null $worksheetTemplate
     * @param bool         $enableOffline
     * @param bool         $enableText
     * @param bool         $enableFiles
     * @param bool         $enablePolicy
     * @param DueWork|null $original
     */
    public function __construct(
        private int $markType,
        private int $instructorMarkType,
        private ?int $gradingScheme,
        private ?int $instructorGradingScheme,
        private ?float $maxMark,
        private ?float $instructorMaxMark,
        private ?array $rubricData,
        ?array $attachedFiles,
        private bool $enableWorksheet,
        private ?string $worksheetTemplate,
        private bool $enableOffline,
        private bool $enableText,
        private bool $enableFiles,
        private bool $enablePolicy,
        private ?DueWork $original
    ) {
        $this->setAttachedFiles($attachedFiles);
    }


    /**
     * @param DueWork $assessment
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws RangeException
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
