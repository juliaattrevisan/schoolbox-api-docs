<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

use Schoolbox\GenericCollection;

class SubjectList
{
    /** @var Subject[]|GenericCollection */
    private $subjects;


    /**
     * @param Subject[]|GenericCollection $subjects
     */
    public function __construct(GenericCollection $subjects)
    {
        $this->subjects = $subjects;
    }


    /**
     * @return Subject[]|GenericCollection
     */
    public function getSubjects()
    {
        return $this->subjects;
    }
}
