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

use PHPUnit\Framework\TestCase;
use \Schoolbox\Learning\Assessment\Project as Project;
use \Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use \Schoolbox\Learning\Assessment\Summator\Sum as SummatorSum;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Resource\Folder\Model as FolderModel;

class DateTest extends TestCase
{
    private $dateFormat = 'Y-m-d H:i:s';

    /**
     * All dates valid
     */
    public function testProjectDates()
    {
        $project = new Project(new SummatorMaximum());
        $project->setContext(new FolderModel());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-28 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }

    /**
     * All dates valid
     */
    public function testDueWorkSubmittedDates()
    {
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setAcceptsMultiple();
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-27 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-30 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }

    /**
     * All dates valid
     */
    public function testDueWorkDates()
    {
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }

    /**
     * All dates valid
     */
    public function testTaskDates()
    {
        $project = new Task();
        $project->setContext(new FolderModel());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }


    /**
     * All dates valid
     */
    public function testQuizDates()
    {
        $project = new Quiz();
        $project->setContext(new FolderModel());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-27 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-30 12:12:12', $this->dateFormat));

        $this->assertTrue($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates1()
    {
        $project = new Project(new SummatorMaximum());
        $project->setContext(new FolderModel());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-30 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-28 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates2()
    {
        $project = new Task();
        $project->setContext(new FolderModel());
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-30 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid dates
     * All dates must be a non-null Alaress_Date_Format
     */
    public function testInvalidDates3()
    {
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setAcceptsMultiple();
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-10-27 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-29 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-30 12:12:12', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }

    /**
     * Invalid publish date
     */
    public function testInvalidPublishDate()
    {
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setAcceptsMultiple();
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
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setAcceptsMultiple();
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
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setAcceptsMultiple();
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
        $project = new DueWork();
        $project->setContext(new FolderModel());
        $project->setAcceptsMultiple();
        $project->setPublishAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setOpenAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setDueAt(new \Alaress_Date_Format('2016-09-26 12:12:12', $this->dateFormat));
        $project->setCloseAt(new \Alaress_Date_Format('2016-09-26 12:12:11', $this->dateFormat));

        $this->assertFalse($project->isValidDates());
    }
}
