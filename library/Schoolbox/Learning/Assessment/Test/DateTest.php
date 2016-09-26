<?php
/**
 * Date
 *
 * This tests the Publish, Open, Due and Close dates are set validly
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Test;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\Project as Project;

use \Schoolbox\Learning\Assessment\Summator\ISummator;
use \Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use \Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use \Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use \Schoolbox\Learning\Assessment\Summator\Minimum as SummatorMinimum;
use \Schoolbox\Learning\Assessment\Summator\Sum as SummatorSum;

use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;

class DateTest extends \PHPUnit_Framework_TestCase
{
    private $dateFormat = 'Y-m-d H:i:s';

    /**
     * All dates valid
     */
    public function testAllDatesValid1()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-27 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-28 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }

    /**
     * All dates valid
     */
    public function testAllDatesValid2()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates1()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(null);
        $project->setOpenAt(null);
        $project->setDueAt(null);
        $project->setCloseAt(null);

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates2()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_NullDate());
        $project->setOpenAt(new \Alaress_Date_NullDate());
        $project->setDueAt(new \Alaress_Date_NullDate());
        $project->setCloseAt(new \Alaress_Date_NullDate());

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates3()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_NullDate());
        $project->setOpenAt(null);
        $project->setDueAt(new \Alaress_Date_NullDate());
        $project->setCloseAt(null);

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates4()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_NullDate());
        $project->setOpenAt('');
        $project->setDueAt(null);
        $project->setCloseAt('2016-01-01 01:01:01');

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates5()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt('2016-01-01 01:01:01');
        $project->setOpenAt('2016-01-01 01:01:01');
        $project->setDueAt('2016-01-01 01:01:01');
        $project->setCloseAt('2016-01-01 01:01:01');

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates6()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt('2016-01-01 01:01:01');
        $project->setOpenAt('2016-01-02 01:01:01');
        $project->setDueAt('2016-01-03 01:01:01');
        $project->setCloseAt('2016-01-04 01:01:01');

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid publish date
     */
    public function testInvalidPublishDate()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:13', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid open date
     */
    public function testInvalidOpenDate()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-26 12:12:14', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid due date
     */
    public function testInvalidDueDate()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-26 12:12:15', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid close date
     */
    public function testInvalidCloseDate()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-26 12:12:11', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Partially-valid settings results in invalid
     */
    public function testInvalidPartial1()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(null);
        $project->setDueAt('');
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Partially-valid settings results in invalid
     */
    public function testInvalidPartial2()
    {
        $project = new Project(new SummatorMaximum());
        $project->setPublishAt('2016-09-29 12:12:12');
        $project->setOpenAt(null);
        $project->setDueAt('2016-09-29 12:12:12');
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }
}