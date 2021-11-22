<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@justinclarke.me>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Alaress\Database\Connector\IConnector;
use Schoolbox\File\Mimetype;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;

class FilesBuilder
{
    private IConnector $db;
    private int $componentId;

    /**
     * FilesBuilder constructor.
     *
     * @param IConnector $db
     */
    public function __construct(IConnector $db)
    {
        $this->db = $db;
    }

    /**
     * get homepageId from the componentID
     *
     * @param int $componentId
     * @returns int
     * @throws Mapper\Exception\NotFound
     */
    public function getHomepage(int $componentId)
    {
        $this->componentId = $componentId;
        //get the homepage Id, from the component and confirm it is a files component
        $statement = $this->db->executeQuery("
            SELECT component_instance.identifier FROM component_instance
                INNER JOIN component_definition
                    ON component_instance.component_definition_id = component_definition.id
                WHERE component_definition.class = 'Component_Homepage_FileListController' AND component_instance.id = :id
            ", ['id' => $componentId]);

        /** @var array|bool $homepage_id */
        $homepage_id = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($homepage_id === false) {
            //component ID does not exist
            throw new \Schoolbox\Mapper\Exception\NotFound();
        }

        return $homepage_id['identifier'];
    }


    /**
     * Get the list of files from a folder, not children just the ones in this folder.
     * We will assume the user has access to this folder
     * and lets build a nice array of the files
     * @param int $folderId
     * @param int $folderAccess
     * @return array of files
     */
    public function getFolderData(int $folderId, int $folderAccess = UserAccess::ACCESS_READ): array
    {
        //get the files, for the source folder
        $fileMapper = \Schoolbox\Resource\File\Mapper::getInstance();
        $fileList = $fileMapper->findForFolders(
            [$folderId],
            [
                'getHidden' => $folderAccess >= UserAccess::ACCESS_WRITE,
            ]
        );

        //get return url, for edit page

        $returnURL = Redirect::encode("/resources/folder/" . $this->componentId . "/files/${folderId}");

        //lets build the list folder array, so we don't have to worry about hydration. etc
        $files = [];
        foreach ($fileList as $file) {
            $files[] = [
                'name'                  => $file->getTitle(),
                'id'                    => $file->getId(),
                'url'                   => '/send.php?id=' . $file->getId(),
                'mime'                  => [
                    'mimeType'          => $file->getStorage()->getMimetype()->getMime(),
                    'iconFile'          => $file->getStorage()->getMimetype()->getIconFile(),
                    'description'       => $file->getStorage()->getMimetype()->getDescription(),
                    'url'               => '/images/mime/' . $file->getStorage()->getMimetype()->getIconFile(),
                ],
                'hidden'                => $file->isHidden(),
                'type'                  => $this->getType($file->getMimetype()),
                'hash'                  => $file->getHash(),
                'size'                  => $file->getSize(),
                'dateModified'          => [
                            'timestamp' => $file->getModifiedAt()->getUnixTimeStamp(),
                            'formatted' => $file->getModifiedAt()->getShort(),
                ],
                'editUrl'              => '/cms/file/' . $file->getId() . "?" . Redirect::RETURN_URL . "=${returnURL}",
            ];
        }
        return $files;
    }

    /**
     * find the supplied component config, or build from the default if required
     * returns array (sourceId, title, sorting, direction, filter)
     * @param int $componentId
     * @param int $homepageId
     * @return array componentConfig
     * @throws Mapper\Exception\NotFound
     */
    public function getComponentConf(int $componentId, int $homepageId): array
    {
        //get the instance config.
        $instanceMapper = \Component_InstanceMapper::getInstance();
        $instanceConfig = $instanceMapper->findByInstanceId($componentId)->getConfigArray();

        //if there is no source, update to the current homepage
        $instanceConfig['src']['value'] = $instanceConfig['src']['value'] ?: $homepageId;
        if ('' === trim($instanceConfig['componentname']['value'])) {
            $instanceConfig['componentname']['value'] =  $instanceConfig['componentname']['defaultValue'];
        }

        //return instance / default config values
        return $instanceConfig;
    }

    /**
     *get Type, returns the string for one of the following:
     *  ['Document', 'Audio', 'Video', 'Image', 'Other']
     * @param Mimetype $mime
     * @return string
     */
    private function getType(Mimetype $mime)
    {
        /**
        //get filters from FileMapper
        // Put together mimetype filters
        $fileTypeMimes = [
            'document' => [
                "%pdf%",
                "%msword%",
                "%vnd.ms%",
                "%rtf%",
                "%text%",
                "%document%",
                "%powerpoint%",
            ],
            'image' => ["%image%",],
            'audio' => ["%audio%",],
            'video' => ["%video%", "%flash%", "%realmedia%",],
        ];
        */
        if ($mime->isImage() === true) {
            return "image";
        } elseif ($mime->isAudio() === true) {
            return "audio";
        } elseif ($mime->isVideo()) {
            return "video";
        } elseif (preg_match('/(pdf)|(msword)|(vnd.ms)|(rtf)|(text)|(document)|(powerpoint)/', $mime->getMime())) {
            return "document";
        }
        //else other??
        return "other";
    }
}
