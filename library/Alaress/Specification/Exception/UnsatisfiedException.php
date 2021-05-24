<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification\Exception;

use Alaress\Specification\ISpecification;
use Throwable;
use UnexpectedValueException;

/**
 * May be thrown when an entity fails a specification pattern.
 *
 * It is up to the discretion of the calling code whether it is appropriate to throw when
 * a candidate does not satisfy a specification.
 */
class UnsatisfiedException extends UnexpectedValueException
{

    /**
     * The entity that failed the spec.
     *
     * @var mixed
     */
    private $candidate;

    /**
     * The spec that the entity failed
     *
     * @var ISpecification
     */
    private $specification;


    /**
     * @param mixed          $candidate
     * @param ISpecification $specification
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($candidate, ISpecification $specification, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->candidate     = $candidate;
        $this->specification = $specification;
        parent::__construct($message, $code, $previous);
    }


    /**
     * @return mixed
     */
    public function getCandidate()
    {
        return $this->candidate;
    }


    /**
     * @return ISpecification
     */
    public function getSpecification(): ISpecification
    {
        return $this->specification;
    }
}
