<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Web\Controller\Base;
use Proxy\Server;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Collection;
use Schoolbox\File\Remote\GDriveFetcher;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * The google file mapper
     */
    private Mapper $googleFileMapper;

    /**
     * UserMapper
     */
    private UserMapper $userMapper;

    /**
     * UserAccessMapper
     */
    private UserAccessMapper $userAccessMapper;

    /**
     * FolderMapper
     */
    private FolderMapper $folderMapper;

    private const ROLE_WRITER    = 'writer';
    private const ROLE_COMMENTER = 'commenter';
    private const ROLE_READER    = 'reader';

    /**
     * Constructor
     * @param Mapper           $googleFileMapper
     * @param UserMapper       $userMapper
     * @param UserAccessMapper $userAccessMapper
     * @param FolderMapper     $folderMapper
     */
    public function __construct(
        Mapper $googleFileMapper,
        UserMapper $userMapper,
        UserAccessMapper $userAccessMapper,
        FolderMapper $folderMapper
    ) {
        $this->googleFileMapper = $googleFileMapper;
        $this->userMapper       = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->folderMapper     = $folderMapper;
    }

    /**
     * @param Model $googleFile
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function getGoogleFile(Model $googleFile)
    {
        $folder = $this->folderMapper->findById($googleFile->getFolderId());

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@googleFile/detail.twig',
                [
                    'googleFile' => $googleFile,
                    'folder'     => $folder,
                ],
                'Google File'
            )
        );
    }

    /**
     * Create a google file from POST data.
     *
     * @param Folder $folder
     * @api   POST /googleFile/{folderId}
     * @since 21.0
     */
    public function postGoogleFile(Folder $folder): void
    {
        $googleIdString = $this->request->post->get('googleIds');
        if (!$googleIdString) {
            $this->getResponse()->redirect->afterPost('/homepage/' . $folder->getId());
            return;
        }

        $selectedFiles      = json_decode($googleIdString, true);
        $editAccessLevel    = (int)$this->request->post->get('gfileEdit');
        $commentAccessLevel = (int)$this->request->post->get('gfileComment');

        $proxy         = new Server($this->getConfigLoader());
        $gDriveFetcher = new GDriveFetcher(
            null,
            null,
            $proxy->curlInit(),
            $this->request->post->get('tokenType') . ' ' . $this->request->post->get('accessToken'),
            false
        );

        $existingGFileIds    = $this->googleFileMapper->findByColumn('folder_id', $folder->getId())
                                                      ->getPropertyValues('googleId');
        $email2accessRoleMap = $this->getEmail2AccessRoleMap($folder->getId(), $editAccessLevel, $commentAccessLevel);
        $errors              = [];
        foreach ($selectedFiles as $selectedFile) {
            // if this file is already associated with this folder, skip
            if (in_array($selectedFile['id'], $existingGFileIds)) {
                continue;
            }
            // store google file id in the db
            try {
                $googleFile = $this->googleFileMapper->createModel();
                $googleFile->setFolderId($folder->getId())
                           ->setGoogleId($selectedFile['id'])
                           ->setName($selectedFile['name'])
                           ->setIcon($selectedFile['icon'])
                           ->setUrl('')
                           ->setOwnerId($this->getUser()->getId());
                $this->googleFileMapper->save($googleFile);
                // provide access to google doc via api
                foreach ($email2accessRoleMap as $email => $accessRole) {
                    $gDriveFetcher->setPermissions(
                        $selectedFile['id'], $accessRole, $email
                    );
                }
                $meta = $gDriveFetcher->fetchMetaData($selectedFile['id']);
                $googleFile->setUrl($meta['webViewLink']);
                $this->googleFileMapper->save($googleFile);
            } catch (MapperException $e) {
                $errors[] = "Failed to save : " . $e->getMessage();
            }
        }

        $this->getResponse()->status->set(200);
        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                [
                    'success'  => true,
                    'messages' => $errors,
                ]
            )
        );
    }

    /**
     * Generates a map of email to google role type
     * @param int $folderId
     * @param int $editAccessLevel
     * @param int $commentAccessLevel
     * @return array
     */
    private function getEmail2AccessRoleMap(int $folderId, int $editAccessLevel, int $commentAccessLevel): array
    {
        $accesses             = $this->userAccessMapper->findByColumnList(
            ['sid' => $folderId, 'service' => UserAccess::SERVICE_HOMEPAGE]
        );
        $users                = $this->userMapper->hydrateCollection(new Collection($accesses->getPropertyValues('user')))->indexBy('id');
        $userAccess2GFileRole = $this->getUserAccessToRoleMap($editAccessLevel, $commentAccessLevel);

        $map = [];
        foreach ($accesses as $access) {
            /** @var User $currentUser */
            $currentUser = $users->getOffset($access->getUser()->getId());
            if (!$currentUser->getEmail()) {
                // this user doesn't have email, ignore
                continue;
            }
            $map[$currentUser->getEmail()] = $userAccess2GFileRole[$access->getAccessLevel()];
        }
        return $map;
    }

    /**
     * Generates a map of folder access level to google role type, based on edit/comment selections
     * @param int $editAccessLevel
     * @param int $commentAccessLevel
     * @return array
     */
    private function getUserAccessToRoleMap(int $editAccessLevel, int $commentAccessLevel): array
    {
        if ($commentAccessLevel > $editAccessLevel) {
            throw new ApplicationException();
        }

        $map = [];
        foreach ([UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL] as $accessLevel) {
            if ($accessLevel >= $editAccessLevel) {
                $map[$accessLevel] = self::ROLE_WRITER;
                continue;
            }

            if ($accessLevel >= $commentAccessLevel) {
                $map[$accessLevel] = self::ROLE_COMMENTER;
                continue;
            }
            $map[$accessLevel] = self::ROLE_READER;
        }
        return $map;
    }
}
