<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\ProfileImages;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Registry;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use SensitiveParameter;

class Download implements IStrategy
{
    private IPhotoDownloader $photoDownloader;

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private Registry $registry,
        private string $host,
        #[SensitiveParameter] private string $key,
        private string $zipDir,
        private UserProfile\Mapper $baseProfileMapper,
        private StorageMapper $storageMapper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function import(): void
    {
        /**
         * if no users exist, let the user know and exit
         */
        if ($this->registry->count() === 0) {
            $this->log->warning('No users to download profile photos for.');
            return;
        }

        // if batch API exists, use that for photos, else fallback to batchxml thumbnails
        $credentials = (new DSN())->decode($this->key);
        if ($credentials['modules'][DSN::MODULE_RESTAPIHOST]['key'] === '') {
            $this->photoDownloader = new BatchPhotoDownloader($this->host, $this->key, $this->zipDir);
        } else {
            /* This lets you shank down zip files containing everyone's profile pictures
         */
            $this->photoDownloader = new RestPhotoDownloader($this->key);
        }

        foreach ($this->registry->recallAll() as $externalId => $data) {
            /**
             * we are only wanting to update the profile photo if
             * the sis photo has been updated,
             * we don't have a photo for the user yet
             */
            // check if the sis photo has been updated...
            try {
                $sisPhotoId = $this->photoDownloader->getLatestPhotoID($data['person']['type'], $externalId);
            } catch (GuzzleException $e) {
                if ($e->getCode() === 404) {
                    // this shouldn't happen, the user existed in the BatchXML but they don't in the RestAPI... weird...
                    $this->log->warning(sprintf("Profile picture: no user found for '%s' %s.",$externalId, $data['person']['type']));
                    continue;
                }
            }
            if (null === $data['person']['portrait'] || $sisPhotoId !== $data['person']['sisPhotoId']) {
                $this->log->info('Downloading profile photo for ' . $externalId);
                $baseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);

                // seeing as we are updating the photo, we need to remember this
                $baseProfile->setMetadata(json_encode(['sisPhotoId' => $sisPhotoId]));

                $this->importPortrait($baseProfile, $data);
            }
        }
    }

    /**
     * Import a profile picture for the given $baseProfile
     *
     * Importing a profile picture requires you to enable photo_from_sis config var
     * ("Load Photos From SIS" needs to be on)
     *
     * This populates the portrait_hash in $baseProfile
     *
     * @param UserProfile\Model  $baseProfile
     * @param array $data // registry cache of the human
     * @return void
     */
    private function importPortrait(UserProfile\Model $baseProfile, array $data): void
    {
        /* Start validating the contents of $fileInfo to ensure we have what we need
         *
         * file_info node might not exist; if it doesn't, skip it
         */
        $photoContents = $this->photoDownloader->getPhoto($data['person']['personId'], $data['person']['type'], $baseProfile->getExternalId());

        if (null === $photoContents) {
            $this->log->debug(sprintf(
                'Profile picture: no data found for %s %s (%s)',
                $baseProfile->getFirstname(),
                $baseProfile->getLastname(),
                $data['person']['personId']
            ));

            return;
        }

        /* Start the part where we save the image
         */
        $this->log->debug(sprintf(
            'Importing profile picture for %s %s (%s)',
            $baseProfile->getFirstname(),
            $baseProfile->getLastname(),
            $baseProfile->getExternalId()
        ));

        /* Only try to persist images if perform mode is activated
         */
        if (!$this->perform) {
            return;
        }

        /* Otherwise, all the parts are here and valid; continue with the persistence
         * to storage and assignation to the $baseProfile by reference
         *
         * Not using $fileInfo['file_name'] and $fileInfo['mime_type'] for now
         */
        try {
            $portrait = $this->storageMapper->acceptStream($baseProfile->getExternalId() . '.jpg', $photoContents);

            /* You get a picture if any of the following occur:
             *     - you don't have a portrait yet; you can get one added
             *     - your existing picture is different to the one we just got; update it
             */
            if (!$baseProfile->getPortrait() || ($baseProfile->getPortrait()->getHash() !== $portrait->getHash())) {
                $this->log->debug(sprintf(
                    'Profile picture: %s %s (%s) has profile hash %s derived from %s.png via %s',
                    $baseProfile->getFirstname(),
                    $baseProfile->getLastname(),
                    $baseProfile->getExternalId(),
                    $portrait->getHash(),
                    $data['person']['personId'],
                    $this->photoDownloader->whichSystem(),
                ));

                $baseProfile->setPortrait($portrait);

                $this->baseProfileMapper->save($baseProfile);
            }
        } catch (\Exception $e) {
            // noop, but warn the cli that something unusual has transpired
            $this->log->warning(sprintf(
                'Profile picture: exception thrown. %s',
                $e->getMessage()
            ));
        }
    }
}
