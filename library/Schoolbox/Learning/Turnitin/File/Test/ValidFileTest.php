<?php

/**
 * ValidFileTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\File\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Turnitin\ValidFile;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 * Test whether the turnitin file checker works in accordnace with their laws
 */
class ValidFileTest extends TestCase
{
    /**
     * @param SubmissionFile $file
     * @param bool           $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidExample')]
    public function testValidExample(SubmissionFile $file, bool $expected): void
    {
        $this->assertEquals($expected, (new ValidFile())->isSatisfiedBy($file));
    }


    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function providerValidExample(): array
    {
        $validTextFile = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('txt')
                ->setSize(1.5 * 1024 * 1024));

        $validOtherFile = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('docx')
                ->setSize(50 * 1024 * 1024));

        $invalidTextFile = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('txt')
                ->setSize(15 * 1024 * 1024));

        $invalidOtherFile = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('docx')
                ->setSize(500 * 1024 * 1024));

        $unknownExtension = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('exe')
                ->setSize(1024));

        $missingExtension = (new SubmissionFile())
                        ->setStorage((new Storage())
                ->setExtension('')
                ->setSize(1024));

        $unknownExtensionExceedsSize = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('exe')
                ->setSize(900 * 1024 * 1024));

        $missingExtensionExceedsSize = (new SubmissionFile())
            ->setStorage((new Storage())
                ->setExtension('')
                ->setSize(900 * 1024 * 1024));

        return [
            'Valid 1: allowed extension, allowed size' => [
                $validTextFile,
                true
            ],

            'Valid 2: allowed extension, allowed size' => [
                $validOtherFile,
                true
            ],

            'Invalid 1: allowed extension, exceeds size' => [
                $invalidTextFile,
                false
            ],

            'Invalid 2: allowed extension, exceeds size' => [
                $invalidOtherFile,
                false
            ],

            'Invalid 3: unknown extension, allowed size' => [
                $unknownExtension,
                false
            ],

            'Invalid 4: missing extension, allowed size' => [
                $missingExtension,
                false
            ],

            'Invalid 5: unknown extension, exceeds size' => [
                $unknownExtensionExceedsSize,
                false
            ],

            'Invalid 6: missing extension, exceeds size' => [
                $missingExtensionExceedsSize,
                false
            ],
        ];
    }
}
