<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

use Alaress_Byte_Format;
use Alaress_Date_Format;
use League\Flysystem\FilesystemInterface;
use Schoolbox\File\Exception as FileException;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource\Storage\Sanitizer\ISanitizer;
use Schoolbox\User;

/**
 * Receiver for accepting files into the storage system.
 */
class Receiver implements IReceiver
{
    /**
     * Constructor
     *
     * @param Mapper              $storageMapper          Mapper for persisting the database record for storage objects.
     * @param MimetypeMapper      $mimetypeMapper         Mapper for retrieving mimetypes for storage objects.
     * @param FilesystemProxy     $storageFilesystemProxy Proxy for a filesystem object, for persisting the actual file
     *                                                    content of storage objects.
     * @param ISanitizer          $sanitizer              Sanitizer for file content of storage objects.
     * @param FilesystemInterface $tempFilesystem         Temporary file system: files will be extracted from here.
     * @param User|null           $currentUser            The current user: used to determine if the file is acceptable.
     */
    public function __construct(
        private Mapper $storageMapper,
        private MimetypeMapper $mimetypeMapper,
        private FilesystemProxy $storageFilesystemProxy,
        private ISanitizer $sanitizer,
        private FilesystemInterface $tempFilesystem,
        private ?User $currentUser,
    ) {
    }

    /**
     * @inheritdoc
     * @throws FileException if the temp file couldn't be created
     */
    public function acceptStream(
        string $basename,
        mixed $fp,
        bool $public = false,
        ?Alaress_Date_Format $expiryAt = null,
        ?User $uploadingUser = null,
    ): Model {
        // This might seem hacky, but to ensure all the safety checks pass, this data needs to be a tempfile
        // (just like an upload)
        $tempLocation = sb_tempnam('otfs');

        /* Temporary location didn't get created. There might be a number of reasons why that might
         * be the case; no write access to the specified location, disk full, etc.
         *
         * CLI Tools:
         * If you ended up following a fatal from a cli tool, you should try running it with sudo
         * Otherwise your logged-in account might not have write access to $tempLocation
         */
        if ('' === $tempLocation) {
            throw new FileException("Temporary location couldn't be created; check sudo, perms, disk usage");
        }

        // Dump the stream into our tempfile

        file_put_contents($tempLocation, $fp);

        return $this->accept($basename, $tempLocation, $public, $expiryAt, $uploadingUser);
    }

    /**
     * @inheritdoc
     */
    public function accept(
        string $basename,
        string $tempLocation,
        bool $public = true,
        ?Alaress_Date_Format $expiryAt = null,
        ?User $uploadingUser = null,
    ): Model {
        $storage = $this->storageMapper->createModel();

        // Set file type metadata
        $storage->setFilename(mb_pathinfo($basename, PATHINFO_FILENAME));
        $storage->setExtension(mb_pathinfo($basename, PATHINFO_EXTENSION));

        try {
            $storage->setMimetype($this->mimetypeMapper->getByFilename($basename));
        } catch (FileException $e) {
            // We are going to allow the mime to remain NULL, which will shortly be caught in the safetyCheck
        }

        // Run the safety check
        $user = $uploadingUser ?? $this->currentUser;
        if ($user) {
            $requireSafety = !$user->isSuperuser();
        } else {
            $requireSafety = false;
        }
        if ($requireSafety) {
            $this->assertStorageUploadable($storage);
        } elseif (!$storage->getMimetype() instanceof Mimetype) {
            // For superusers only, we allow an unknown mimetype
            $storage->setMimetype($this->mimetypeMapper->getUnknownMime());
        }

        // By default files uploaded this way are public
        $storage->setPublic($public);

        // TODO: Find a way to remove the need for this truncation
        $tempLocationInFilesystem = str_replace(sys_get_temp_dir(), '', $tempLocation);
        $helper                   = new Sanitizer\Helper($this->sanitizer);
        $sanitized                = $helper->sanitize(
            $this->tempFilesystem,
            $tempLocationInFilesystem,
            $storage->getMime(),
        );
        if (isset($sanitized)) {
            // write the new content
            $this->storageFilesystemProxy->write($storage, $sanitized);
        } else {
            // write the old content
            $this->storageFilesystemProxy->writeFromFilesystemAndLocation(
                $storage,
                $this->tempFilesystem,
                $tempLocationInFilesystem,
            );
        }

        if (null !== $expiryAt) {
            $storage->setExpiryAt($expiryAt);
        }

        $this->storageMapper->save($storage);

        return $storage;
    }

    /**
     * Safety check that a file may be uploaded; checks that:
     * * the file has a recognised and allowed mimetype
     * * the file is not larger than the file's allowed size
     *
     * Skipped if the receiver is run in a CLI (where there is no user context),
     * or the current user is a superuser.
     *
     * @param Model $storage
     *
     * @throws FileException if the file isn't uploadable for any reason
     */
    private function assertStorageUploadable(Model $storage): void
    {
        $fileMime    = $storage->getMimetype();
        $errorSuffix = "Try a different file or contact your IT administrator.";

        // Assert: Mime set
        if (!$fileMime instanceof Mimetype) {
            throw new FileException(
                "This file has no detectable type. {$errorSuffix}",
                FileException::CODE_MIMETYPE_NOT_FOUND
            );
        }

        // Assert: Mime type allowed
        if (!$fileMime->isEnabled() || $fileMime->isDeleted()) {
            throw new FileException(
                "This file is not a valid file type. {$errorSuffix}",
                FileException::CODE_MIMETYPE_NOT_ALLOWED
            );
        }

        // Assert: Acceptable size
        if ($fileMime->getActualMaxFileSize() < $storage->getSize()) {
            throw new FileException(
                sprintf(
                    "This file exceeds the file size restriction of %s. %s",
                    Alaress_Byte_Format::bytesToString($fileMime->getActualMaxFileSize()),
                    $errorSuffix,
                ),
            );
        }
    }
}
