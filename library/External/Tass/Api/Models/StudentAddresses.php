<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

use Schoolbox\GenericCollection;

class StudentAddresses
{

    /** @var StudentAddress[]|GenericCollection */
    private $studentAddresses;


    public function __construct(GenericCollection $studentAddresses)
    {
        $this->studentAddresses = $studentAddresses;
    }


    /**
     * @return StudentAddress[]|GenericCollection
     */
    public function getStudentAddresses()
    {
        return $this->studentAddresses;
    }
}
