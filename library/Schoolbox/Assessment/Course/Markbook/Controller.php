<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Schoolbox\Learning\Course\DataModel\Specification;

class Controller
{

    /**
     * Displays the Course Markbook page
     *
     * @param Specification\Model $courseSpec
     * @level 18.0
     */
    public function getCourseMarkbook(Specification\Model $courseSpec): void
    {
        // TODO implement method
        echo 'function: ' . __FUNCTION__ . "<br>\n";
        echo 'class: ' . \get_class($courseSpec) . "<br>\n";
        echo "id: {$courseSpec->getId()}<br>\n";
        die();
    }


    /**
     * Loads a filtered report into the Course Markbook page
     *
     * @param Specification\Model $courseSpec
     * @level 18.0
     * @todo  Condense this and the GET route into a single route and distinguish via SYNC vs AJAX
     */
    public function postCourseMarkbook(Specification\Model $courseSpec): void
    {
        // TODO implement method
        echo 'function: ' . __FUNCTION__ . "<br>\n";
        echo 'class: ' . \get_class($courseSpec) . "<br>\n";
        echo "id: {$courseSpec->getId()}<br>\n";
        die();
    }
}
