<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@justinclarke.me>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Mapper;

class FilesBuilder
{
    /**
    * @var IConnector
    */
    private $db;

    /**
     * query to get the files component defaults
     * @var string
     */
    private $defaultConf_query = "
         SELECT component_config_definition.label, component_config_definition.key, component_config_definition.default_value
         FROM component_config_definition
         INNER JOIN component_definition
         ON component_definition.id = component_config_definition.component_definition_id
         WHERE component_definition.class = 'Component_Homepage_FileListController'";

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
        //get the homepage Id, from the component and confirm it is a files component
        $statement = $this->db->executeQuery("
            SELECT component_instance.identifier, folder.name FROM component_instance
                INNER JOIN component_definition
                    ON component_instance.component_definition_id = component_definition.id
                INNER JOIN folder
                    ON folder.id= component_instance.identifier
                WHERE component_definition.class = 'Component_Homepage_FileListController' AND component_instance.id = :id
            ", ['id' => $componentId]);

        $homepage_id = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($homepage_id === false) {
            //component ID does not exist
            throw new \Schoolbox\Mapper\Exception\NotFound();
        }

        return $homepage_id;
    }


    /**
     * Get the list of files from a folder, not children just the ones in this folder.
     * We will assume the user has access to this folder
     * and lets build a nice array of the files
     * @param int $folderId
     * @return array of files
     */
    public function getFolderData(int $folderId): array
    {
        //get the files, for the source folder
        $fileMapper = \Schoolbox\Resource\File\Mapper::getInstance();
        $fileList = $fileMapper->findForFolders(
            [$folderId],
        );

        //lets build the list folder array, so we don't have to worry about hydration. etc
        $files = [];
        foreach ($fileList as $file) {
            $files[] = [
                'name'                  => $file->getTitle(),
                'file_id'               => $file->getId(),
                'mime'                  => $file->getStorage()->getMimetype(),
                'hash'                  => $file->getHash(),
                'size'                  => $file->getSize(),
                'dateAdded'             => [
                            'timestamp' => $file->getCreatedAt()->getUnixTimeStamp(),
                            'formatted' => $file->getCreatedAt()->getShort(),
                ],
                'dateModified'          => [
                            'timestamp' => $file->getModifiedAt()->getUnixTimeStamp(),
                            'formatted' => $file->getModifiedAt()->getShort(),
                ],
            ];
        }
        return $files;
    }

    /**
     * find the supplied component config, or build from the default if required
     * returns array (sourceId, title, sorting, direction, filter)
     * @param int $componentId
     * @return array componentConfig
     * @throws Mapper\Exception\NotFound
     */
    public function getComponentConf(int $componentId): array
    {
        /**
         * Need to get the 'definitions' out of the db for components.
         * ```
         * default config object
         * select component_config_definition.label, component_config_definition.key, component_config_definition.default_value
         * from component_config_definition
         * inner join component_definition
         * on component_definition.id = component_config_definition.component_definition_id
         * where component_definition.class = 'Component_Homepage_FileListController'
         * ```

         * ```
         * instance config object
         * select component_config_definition.label, component_config_definition.key, component_config_instance.value
         * from component_config_definition
         * inner join component_config_instance
         * on component_config_instance.component_config_definition_id = component_config_definition.id
         * where component_config_instance.component_instance_id = :id
         * ```
         */

        $homepage_id = $this->getHomepage($componentId);

        //build default config object / query
        $defaultComponentConfig = [];
        $statement = $this->db->executeQuery($this->defaultConf_query);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $defaultComponentConfig[$row['key']] = [
            'label' => $row['label'],
            'default_value' => $row['default_value'],
            ];
        }

        //build instance config object / query
        $query = "
        SELECT component_config_definition.label, component_config_definition.key, component_config_instance.value, component_instance.identifier
        FROM component_config_definition
        INNER JOIN component_config_instance
                    ON component_config_instance.component_config_definition_id = component_config_definition.id
        INNER JOIN component_instance
                    ON component_config_instance.component_instance_id = component_instance.id
        WHERE component_config_instance.component_instance_id = :id
         ";

        $statement = $this->db->executeQuery($query, ['id' => $componentId]);

        $instanceComponentConfig = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $instanceComponentConfig['homepage'] = $row['identifier'];
            $instanceComponentConfig[$row['key']] = [
                'label' => $row['label'],
                'value' => $row['value'],
            ];
        }

        //build the final merged config between the instance object, and if needed add the defaults.
        $config = [];
        $config['homepage'] = $instanceComponentConfig['homepage'] ?: $homepage_id['identifier'];

        foreach ($defaultComponentConfig as $key => $default) {
            $config[$key] = [
                'label' => $instanceComponentConfig[$key]['label'] ?: $default['label'],
                'value' => $instanceComponentConfig[$key]['value'] ?: $default['default_value'],
            ];
        }
        //if there is no config, the source will be empty, so set it now
        $config['src']['value'] = $config['src']['value'] ?: $config['homepage'];

        //return instance / default config values
        return $config;
    }
}
