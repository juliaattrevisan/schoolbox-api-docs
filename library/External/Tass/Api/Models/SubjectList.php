<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
