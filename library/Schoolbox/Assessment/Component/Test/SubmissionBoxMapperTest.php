<?php

/**
 * Unit tests for Schoolbox\Assessment\Component\SubmissionBoxMapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\Test;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Test\TestCase;

class SubmissionBoxMapperTest extends TestCase
{
    /**
     * @var SubmissionBoxMapper
     */
    protected $object;

    public function setUp(): void
    {
        parent::setUp();
        $this->object = SubmissionBoxMapper::getInstance();
        $this->object->setConfigLoader(new ArrayLoader());
    }

    public function testHydrate()
    {
        $data = array(
            'id'                      => 3,
            'owner'                   => 15,
            'accept_submission'       => true,
            'multi_submission'        => false,
            'enable_worksheet'        => true,
            'worksheet_template_hash' => 'thisIsNotAHash',
            'enable_offline'          => true,
            'enable_text'             => true,
            'enable_files'            => true,
            'enable_policy'           => true,
            'max_mark'                => 100,
            'self_reviewability'      => SubmissionBox::SELF_REVIEWABILITY_NONE,
        );

        $box = $this->object->hydrate($data);
        $this->assertInstanceOf('Schoolbox\\Assessment\\Component\\SubmissionBox', $box);
        $this->assertEquals(3, $box->getId());
        $this->assertEquals(15, $box->getOwner()->getId());
        $this->assertEquals(true, $box->getAcceptOnline());
        $this->assertEquals(false, $box->getAcceptMultiple());
        $this->assertEquals(true, $box->getEnableWorksheet());
        $this->assertEquals('thisIsNotAHash', $box->getWorksheetTemplate()->getHash());
        $this->assertEquals(true, $box->getEnableOffline());
        $this->assertEquals(true, $box->getEnableText());
        $this->assertEquals(true, $box->getEnableFiles());
        $this->assertEquals(true, $box->getEnablePolicy());
        $this->assertEquals(100, $box->getMaxMark());
    }

    public function testFindById()
    {
        $this->markTestIncomplete();
    }

    public function testSave()
    {
        $this->markTestIncomplete();
    }

    public function testDelete()
    {
        $this->markTestIncomplete();
    }
}
