<?php

/**
 * Unit tests for Schoolbox\Assessment\Component\SubmissionBox class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\Test;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Test\TestCase;

class SubmissionBoxTest extends TestCase
{
    /**
     * @var SubmissionBox
     */
    protected $object;

    public function setUp(): void
    {
        parent::setUp();
        $this->object = new SubmissionBox();
    }

    /**
     * Marks rubric support as incomplete until a rubric model exists.
     */
    public function testRubric(): void
    {
        $this->markTestIncomplete('Rubric model hasn\'t been created yet');
    }

    /**
     * Marks category support as incomplete until category/homepage models exist.
     */
    public function testCategory(): void
    {
        $this->markTestIncomplete('Category/Homepage models haven\'t been created yet');
    }

    /**
     * Marks work type support as incomplete until a work type model exists.
     */
    public function testWorkType(): void
    {
        $this->markTestIncomplete('Work type model hasn\'t been added yet');
    }

    /**
     * Marks mark type support as incomplete until a mark type model exists.
     */
    public function testMarkType(): void
    {
        $this->markTestIncomplete('Mark type model hasn\'t been added yet');
    }

    /**
     * Tests that the MARK_VISIBILITY_STRINGS map contains the correct human-readable labels for each visibility constant.
     */
    public function testMarkVisibilityStringsMapCorrectly(): void
    {
        $strings = IAssessable::MARK_VISIBILITY_STRINGS;
        $this->assertIsString(array_key_first($strings), 'MARK_VISIBILITY_STRINGS must be keyed by string constants, not integers');
        $this->assertCount(4, $strings);
        $this->assertSame('Staff Only', $strings[IAssessable::MARK_VISIBILITY_PRIVATE]);
        $this->assertSame('Staff & Student', $strings[IAssessable::MARK_VISIBILITY_STUDENT]);
        $this->assertSame('Staff & Parent', $strings[IAssessable::MARK_VISIBILITY_PARENT]);
        $this->assertSame('Staff, Student & Parent', $strings[IAssessable::MARK_VISIBILITY_PARENT_STUDENT]);
    }

    /**
     * Tests that isMarkShownToStudents() returns true only for visibility settings that include students.
     */
    public function testIsMarkShownToStudents(): void
    {
        $box = new SubmissionBox();

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);
        $this->assertFalse($box->isMarkShownToStudents());

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_STUDENT);
        $this->assertTrue($box->isMarkShownToStudents());

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT);
        $this->assertFalse($box->isMarkShownToStudents(), 'Staff & Parent must NOT be visible to students');

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $this->assertTrue($box->isMarkShownToStudents());
    }

    /**
     * Tests that isMarkShownToParents() returns true only for visibility settings that include parents.
     */
    public function testIsMarkShownToParents(): void
    {
        $box = new SubmissionBox();

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);
        $this->assertFalse($box->isMarkShownToParents());

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_STUDENT);
        $this->assertFalse($box->isMarkShownToParents());

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT);
        $this->assertTrue($box->isMarkShownToParents(), 'Staff & Parent must be visible to parents');

        $box->setMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $this->assertTrue($box->isMarkShownToParents());
    }
}
