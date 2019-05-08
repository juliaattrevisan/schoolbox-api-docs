<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      hhttps://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Classes;

class ClassCode
{
    private $classCode;

    public function __construct(string $classCode)
    {
        $this->classCode = urldecode($classCode);
    }

    /**
     * Class codes may be passed in as a comma-separated string. And this returns a URL decoded class code.
     *
     * @return string
     */
    public function getClassCode(): string
    {
        return $this->classCode;
    }

    /**
     * Class codes may be passed in as a comma-separated string.
     * Passing in the first class code in the list ought to be sufficient for finding the class folder,
     * because there can only be one extant class folder per code.
     *
     * @return string
     */
    public function proper(): string
    {
        if (strpos($this->classCode, ',')) {
            $codeArray = explode(',', $this->classCode);

            return $codeArray[0];
        } else {

            return $this->classCode;
        }
    }
}
