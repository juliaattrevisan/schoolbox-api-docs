<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\File\Mimetype;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\ValidUploadableFile;

/**
 * @covers \Schoolbox\Resource\Storage\ValidUploadableFile
 */
class ValidUploadableFileTest extends TestCase
{
    /**
     * @dataProvider getTestIsSatisfiedByValidCaseData
     * @covers \Schoolbox\Resource\Storage\ValidUploadableFile::isSatisfiedBy
     * @param ValidUploadableFile $validator
     * @param Model               $file
     * @return void
     * @noinspection PhpDocMissingThrowsInspection this should never throw
     */
    public function testIsSatisfiedByValidCase(ValidUploadableFile $validator, Model $file)
    {
        /** @noinspection PhpUnhandledExceptionInspection this should never throw */
        $this->assertTrue($validator->isSatisfiedBy($file));
    }

    /**
     * Data provider for `testIsSatisfiedByValidCase`
     *
     * @return iterable
     */
    public function getTestIsSatisfiedByValidCaseData(): iterable
    {
        $standardMimetype = (new Mimetype())
            ->setMime('text/plain')
            ->setMaxFileSize(1_000_000)
            ->setEnable(true)
            ->setDeleted(false);
        $standardFile = (new Model())
            ->setExpiryAt(null)
            ->setMimetype($standardMimetype);

        yield 'file under size limit, no expiry' => [
            new ValidUploadableFile(1_000, Alaress_Date_Format::now()),
            $standardFile,
        ];
        yield 'file under size limit, expiry before latest temp date' => [
            new ValidUploadableFile(1_000_000, Alaress_Date_Format::now()->addDays(1)),
            (clone $standardFile)->setExpiryAt(Alaress_Date_Format::now()->addDays(1)),
        ];
        yield 'file under size limit, expiry after latest temp date' => [
            new ValidUploadableFile(1_000_000, Alaress_Date_Format::now()->addDays(1)),
            (clone $standardFile)->setExpiryAt(Alaress_Date_Format::now()->addDays(2)),
        ];
        yield 'file over size limit, expiry before latest temp date' => [
            new ValidUploadableFile(1_000_001, Alaress_Date_Format::now()->addDays(1)),
            (clone $standardFile)->setExpiryAt(Alaress_Date_Format::now()->addDays(1)),
        ];
    }
}
