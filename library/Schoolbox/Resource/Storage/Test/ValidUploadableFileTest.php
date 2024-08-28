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

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Storage\ValidUploadableFile::class)]
class ValidUploadableFileTest extends TestCase
{
    /**
     * Constant base time: required for test environment, as
     * `Alaress_Date_Format::now()` doesn't return a consistent time in test run
     * contexts.
     *
     * @var Alaress_Date_Format|null
     */
    private static ?Alaress_Date_Format $baseTime = null;

    /**
     * @param Model $file
     * @return void
     * @noinspection PhpDocMissingThrowsInspection this should never throw
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByValidCaseData')]
    public function testIsSatisfiedByValidCase(Model $file)
    {
        /** @noinspection PhpUnhandledExceptionInspection this should never throw */
        $this->assertTrue((new ValidUploadableFile(self::createBaseTime()))->isSatisfiedBy($file));
    }

    /**
     * Data provider for `testIsSatisfiedByValidCase`
     *
     * @return iterable
     */
    public static function getTestIsSatisfiedByValidCaseData(): iterable
    {
        $standardMimetype = (new Mimetype())
            ->setMime('text/plain')
            ->setMaxFileSize(1_000_000)
            ->setEnable(true)
            ->setDeleted(false);
        $standardFile = (new Model())
            ->setExpiryAt(null)
            ->setMimetype($standardMimetype)
            ->setSize(1_000);

        yield 'file under size limit, no expiry' => [
            $standardFile,
        ];
        yield 'file under size limit, expiry before latest temp date' => [
            (clone $standardFile)
                ->setSize(1_000_000)
                ->setExpiryAt(self::createBaseTime()->addDays(15)),
        ];
        yield 'file under size limit, expiry after latest temp date' => [
            (clone $standardFile)
                ->setSize(1_000_000)
                ->setExpiryAt(self::createBaseTime()->addDays(15)->addSeconds(1)),
        ];
        yield 'file over size limit, expiry before latest temp date' => [
            (clone $standardFile)
                ->setSize(1_000_001)
                ->setExpiryAt(self::createBaseTime()->addDays(15)),
        ];
    }

    /**
     * @param Model  $file
     * @param string $errorPattern
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByInvalidCaseData')]
    public function testIsSatisfiedByInvalidCase(
        Model $file,
        string $errorPattern,
    ) {
        $this->expectException(FileException::class);
        $this->expectExceptionMessageMatches($errorPattern);
        ((new ValidUploadableFile(self::createBaseTime()))->isSatisfiedBy($file));
    }

    /**
     * Data provider for `testIsSatisfiedByInvalidCase`
     *
     * @return iterable
     */
    public static function getTestIsSatisfiedByInvalidCaseData(): iterable
    {
        $standardMimetype = (new Mimetype())
            ->setMime('text/plain')
            ->setMaxFileSize(1_000_000)
            ->setEnable(true)
            ->setDeleted(false);
        $standardFile = (new Model())
            ->setExpiryAt(null)
            ->setSize(1_000);

        yield 'file has no mimetype' => [
            $standardFile,
            '/^this file has no detectable type/i',
        ];
        yield 'file mimetype is not enabled' => [
            (clone $standardFile)
                ->setMimetype((clone $standardMimetype)->setEnable(false)),
            '/^this file is not a valid file type/i',
        ];
        yield 'file mimetype is deleted' => [
            (clone $standardFile)
                ->setMimetype((clone $standardMimetype)->setDeleted(true)),
            '/^this file is not a valid file type/i',
        ];
        yield 'file over size limit, no expiry' => [
            (clone $standardFile)
                ->setMimetype($standardMimetype)
                ->setSize(1_000_001),
            '/^this file exceeds the file size restriction/i',
        ];
        yield 'file over size limit, expiry after latest temp date' => [
            (clone $standardFile)
                ->setMimetype($standardMimetype)
                ->setSize(1_000_001)
                ->setExpiryAt(self::createBaseTime()->addDays(15)->addSeconds(1)),
            '/^this file exceeds the file size restriction/i',
        ];
    }

    /**
     * Create a base time object, if this test doesn't have one already.
     *
     * @return Alaress_Date_Format
     */
    private static function createBaseTime(): Alaress_Date_Format
    {
        self::$baseTime ??= Alaress_Date_Format::now();
        return (clone self::$baseTime);
    }
}
