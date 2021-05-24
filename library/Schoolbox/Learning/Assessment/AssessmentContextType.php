<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;

/**
 * Encapsulates the mapping between AssessmentContext Types and:
 * * URL fragments
 * * Twig template directories, and
 * * Radio button values in twig
 *
 * This class essentially enumerates the concrete implementations of AssessmentContext
 * Does not map to DB table as that relationship is represented in join tables
 *
 * Does not belong on AssessmentContext as interface cannot have behaviour (and shouldn't know about implementors)
 */
class AssessmentContextType implements IType
{
    use TypeTrait;

    // Template Directories / URL Fragments / Radio Values
    // (for now, these are both the same values. Could trivially split into use-case-specific values if desired)
    const TYPE_FOLDER = 'folder';
    const TYPE_PROJECT = 'project';
    const TYPE_UNIT_IMPLEMENTATION = 'unit';
    const TYPE_UNIT_SPECIFICATION = 'unitspec';

    /**
     * @var static
     */
    private static $instance;


    /**
     * Protected constructor to enforce singleton pattern
     */
    protected function __construct()
    {
    }

    /**
     * Retrieves the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Get twig template directory for a given AssessmentContext
     *
     * @param AssessmentContext $context
     * @return string
     * @throws \UnexpectedValueException
     */
    public static function objectToString(AssessmentContext $context)
    {
        if ($context instanceof Folder) {
            return self::TYPE_FOLDER;
        } elseif ($context instanceof Project) {
            return self::TYPE_PROJECT;
        } elseif ($context instanceof UnitImplementation) {
            return self::TYPE_UNIT_IMPLEMENTATION;
        } elseif ($context instanceof UnitSpecification) {
            return self::TYPE_UNIT_SPECIFICATION;
        }
        throw new \UnexpectedValueException('Unknown AssessmentContext: ' . get_class($context));
    }


    /**
     * Get the numeric representation from string representation
     *
     * @param string $stringVal
     * @return AssessmentContext
     * @throws \UnexpectedValueException
     */
    public static function stringToObject($stringVal)
    {
        if (self::TYPE_FOLDER === $stringVal) {
            return new Folder();
        } elseif (self::TYPE_PROJECT === $stringVal) {
            return new Project();
        } elseif (self::TYPE_UNIT_IMPLEMENTATION === $stringVal) {
            return new UnitImplementation();
        } elseif (self::TYPE_UNIT_SPECIFICATION === $stringVal) {
            return new UnitSpecification();
        }
        throw new \UnexpectedValueException("Unknown AssessmentContext: $stringVal");
    }

    /* Getters/Setters
     */

    /**
     * Returns the available string values
     * @return string[]
     */
    protected function toArray()
    {
        return [
            self::TYPE_FOLDER,
            self::TYPE_PROJECT,
            self::TYPE_UNIT_IMPLEMENTATION,
            self::TYPE_UNIT_SPECIFICATION,
        ];
    }
}
