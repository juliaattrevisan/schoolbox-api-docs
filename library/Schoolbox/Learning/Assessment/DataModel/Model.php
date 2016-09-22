<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class Model
 */
class Model
{
    use Id;

    // TODO add created/updated/deleted?

    /**
     * A descriptive title of the assessment.
     *
     * @var string
     */
    protected $title;

    /**
     * A longer description of the assessment.
     *
     * @var string
     */
    protected $description;

    /**
     * The date/time at which this assessment become visible to its participants.
     *
     * @var Alaress_Date_Format
     */
    protected $publishDate;

    /**
     * The date/time at which participants able to create responses to this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * The date/time at which participants are no longer able to create responses to this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * The comparative amount that this assessment affects a student's grade by.
     *
     * @var int
     */
    protected $weight = 0;

    /**
     * The sort order of this assessment within its container
     * (a class page, course specification or implementation or project).
     *
     * Assessments are sorted by sequence in ascending order.
     *
     * @var int
     */
    protected $sequence = 0;
}
