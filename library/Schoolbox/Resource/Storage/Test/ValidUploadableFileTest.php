<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\File\Exception as FileException;
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

    /**
     * @dataProvider getTestIsSatisfiedByInvalidCaseData
     * @covers \Schoolbox\Resource\Storage\ValidUploadableFile::isSatisfiedBy
     * @param ValidUploadableFile $validator
     * @param Model               $file
     * @param string              $errorPattern
     * @return void
     */
    public function testIsSatisfiedByInvalidCase(
        ValidUploadableFile $validator,
        Model $file,
        string $errorPattern,
    ) {
        $this->expectException(FileException::class);
        $this->expectExceptionMessageMatches($errorPattern);
        $validator->isSatisfiedBy($file);
    }

    /**
     * Data provider for `testIsSatisfiedByInvalidCase`
     *
     * @return iterable
     */
    public function getTestIsSatisfiedByInvalidCaseData(): iterable
    {
        $standardMimetype = (new Mimetype())
            ->setMime('text/plain')
            ->setMaxFileSize(1_000_000)
            ->setEnable(true)
            ->setDeleted(false);
        $standardFile = (new Model())
            ->setExpiryAt(null);

        yield 'file has no mimetype' => [
            new ValidUploadableFile(1_000, Alaress_Date_Format::now()),
            $standardFile,
            '/^this file has no detectable type/i',
        ];
        yield 'file mimetype is not enabled' => [
            new ValidUploadableFile(1_000, Alaress_Date_Format::now()),
            (clone $standardFile)
                ->setMimetype((clone $standardMimetype)->setEnable(false)),
            '/^this file is not a valid file type/i',
        ];
        yield 'file mimetype is deleted' => [
            new ValidUploadableFile(1_000, Alaress_Date_Format::now()),
            (clone $standardFile)
                ->setMimetype((clone $standardMimetype)->setDeleted(true)),
            '/^this file is not a valid file type/i',
        ];
        yield 'file over size limit, no expiry' => [
            new ValidUploadableFile(1_000_001, Alaress_Date_Format::now()),
            (clone $standardFile)
                ->setMimetype($standardMimetype),
            '/^this file exceeds the file size restriction/i',
        ];
        yield 'file over size limit, expiry after latest temp date' => [
            new ValidUploadableFile(1_000_001, Alaress_Date_Format::now()->addDays(1)),
            (clone $standardFile)
                ->setMimetype($standardMimetype)
                ->setExpiryAt(Alaress_Date_Format::now()->addDays(2)),
            '/^this file exceeds the file size restriction/i',
        ];
    }
}
