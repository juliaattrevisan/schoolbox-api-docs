<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Registry;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Metadata;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use SensitiveParameter;

class Download implements IStrategy
{
    private IPhotoDownloader $photoDownloader;

    /**
     * The photo downloader chooses between the BatchXML zip archive and
     * interrogating the iSAMS REST API per-person to obtain their profile
     * picture. The BatchXML zip will be used if REST API isn't configured.
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param Registry $registry
     * @param string $host
     * @param string $key
     * @param string $zipDir
     * @param UserProfile\Mapper $baseProfileMapper
     * @param StorageMapper $storageMapper
     */
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

        /* Optionally use the iSAMS REST API to fetch higher-quality portrait images since the batchxml
         * gives you low res thumbnails - this will be the fallback if schools don't configure their
         * iSAMS REST API.
         */
        $credentials = (new DSN())->decode($this->key);

        if (trim($credentials['modules'][DSN::MODULE_RESTAPI]['host'] ?? '') === '') {
            /* This lets you shank down zip files containing everyone's profile pictures
             */
            $this->photoDownloader = new BatchPhotoDownloader($this->host, $credentials, $this->zipDir);
        } else {
            /* Obtain each person's photo individually
             */
            $this->photoDownloader = new RestPhotoDownloader($credentials);
        }

        $this->log->debug(sprintf(
            'Using %s for profile photos.',
            get_class($this->photoDownloader)
        ));

        // cache found users, so we can update their profile photos
        $users = $this->baseProfileMapper->findByColumn('external_id', $this->registry->keys());

        foreach ($this->registry->recallAll() as $externalId => $data) {
            /**
             * we are only wanting to update the profile photo if
             * the sis photo has been updated,
             * we don't have a photo for the user yet
             */
            try {
                $sisPhotoId = $this->photoDownloader->getLatestPhotoID($data['person']['type'], $externalId);
            } catch (GuzzleException $e) {
                $this->log->info(sprintf(
                    'Could not obtain portrait picture for %s %s (%s). Skipping',
                    $data['person']['firstname'],
                    $data['person']['lastname'],
                    $externalId
                ));

                /* Log something more verbose so we can debug better
                 */
                $this->log->debug($e->getMessage());

                continue;
            }

            /* We can detect when an identifier is different in isams than what we previously knew.
             * This gives us a clue as to when a profile picture is different than before.
             */
            if (null === $data['person']['portrait'] || $sisPhotoId !== $data['person']['sisPhotoId']) {
                $this->log->info(sprintf(
                    'Downloading profile photo for %s %s (%s)',
                    $data['person']['firstname'],
                    $data['person']['lastname'],
                    $externalId
                ));

                /** @var UserProfile\Model $baseProfile */
                $baseProfile = $users->getBy('externalId', $externalId);

                // seeing as we are updating the photo, we need to remember this
                $baseProfile->setMetadata((new Metadata\Builder())->build(['profile' => [
                    'sisPhotoId' => $sisPhotoId
                ]]));

                $this->importPortrait($baseProfile, $data);
            } else {
                $this->log->debug(sprintf(
                    'Profile picture: Cached image is latest for %s %s (%s) %s',
                    $data['person']['firstname'],
                    $data['person']['lastname'],
                    $externalId,
                    $data['person']['type']
                ));
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
                if ($this->photoDownloader instanceof RestPhotoDownloader) {
                    /* REST API portrait images come from one of four endpoints
                     * which we don't know from here
                     */
                    $this->log->debug(sprintf(
                        'Profile picture: %s %s (%s) has profile hash %s via REST API',
                        $baseProfile->getFirstname(),
                        $baseProfile->getLastname(),
                        $baseProfile->getExternalId(),
                        $portrait->getHash(),
                    ));
                } else {
                    /* BatchXML portrait images come from files in a zip, so we can directly correlate them
                     */
                    $this->log->debug(sprintf(
                        'Profile picture: %s %s (%s) has profile hash %s derived from %s.png via BatchXML zip',
                        $baseProfile->getFirstname(),
                        $baseProfile->getLastname(),
                        $baseProfile->getExternalId(),
                        $portrait->getHash(),
                        $data['person']['personId'],
                    ));
                }

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
