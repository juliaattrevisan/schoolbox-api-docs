<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Config;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper;

/**
 * Class ConfigMapper
 *
 * @package Schoolbox\Config
 * @method Config findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Config[] findByColumn($columnName, $columnValue, $options = array())
 */
class ConfigMapper extends Mapper
{

    // Table definition
    public const TABLE_NAME = 'config';

    public const COL_ID          = 'configID';
    public const COL_NAME        = 'name';
    public const COL_VALUE       = 'value';
    public const COL_TITLE       = 'title';
    public const COL_DESCRIPTION = 'description';
    public const COL_FIELD       = 'field_id';
    public const COL_OPTIONS     = 'options';
    public const COL_TYPE        = 'type_id';
    public const COL_EDITABLE    = 'editable';
    public const COL_HIDDEN      = 'hidden';
    public const COL_REQUIRED    = 'required';
    public const COL_MODULE      = 'module';

    // Module column enum
    public const MODULE_SETTINGS   = 'settings';
    public const MODULE_COMMS      = 'comms';
    public const MODULE_CUSTOM     = 'custom';
    public const MODULE_OAUTH      = 'oauth';
    public const MODULE_SCHOOLDATA = 'schoolData';

    // Field enum (kinda)
    public const FIELD_INPUT         =  1;
    public const FIELD_TEXT_AREA     =  2;
    public const FIELD_BOOLEAN       =  3;
    public const FIELD_SELECT        =  4;
    public const FIELD_CHECKBOX      =  5;
    public const FIELD_BYTES         =  6;
    public const FIELD_SELECT_KEY    =  7;
    public const FIELD_HTML          =  8;
    public const FIELD_RESOURCE      =  9;
    public const FIELD_SELECT_LOOKUP = 10;
    public const FIELD_COLOUR        = 11;
    public const FIELD_FILE          = 12;
    public const FIELD_FILE_MULTI    = 13;
    public const FIELD_PASSWORD      = 14;
    public const FIELD_TIME_PICKER   = 15;
    public const FIELD_NUMBER        = 16;

    // Type enum
    public const TYPE_SITE          =  1;
    public const TYPE_SECURITY      =  2;
    public const TYPE_PATHS         =  3;
    public const TYPE_RESOURCES     =  4;
    public const TYPE_POLICY        =  5;
    public const TYPE_LOCALISATION  =  6;
    public const TYPE_EXTERNAL      =  7;
    public const TYPE_EMAIL         =  8;
    public const TYPE_COLOURS       =  9;
    public const TYPE_3RD           = 10;
    public const TYPE_MODULES       = 11;
    public const TYPE_BOARD         = 12;
    public const TYPE_WELCOME       = 13;
    public const TYPE_DEBUGGING     = 14;
    public const TYPE_LMS_POLICY    = 15;
    public const TYPE_LDAP          = 16;
    public const TYPE_PROXY         = 17;
    public const TYPE_SKIN_CONFIG   = 18;
    public const TYPE_PASTORAL_CARE = 19;
    public const TYPE_DOC_MANAGE    = 20;
    public const TYPE_SAML          = 21;

    // Technically these should be prefixed with 'NAME_', but it gets a bit verbose for little extra clarity
    public const _GLOBAL_MODERATION              = 'global_moderation';
    public const _PUBLISH_DATE_OFFSET            = 'publish_date_offset';
    public const _EXPIRY_DATE_OFFSET             = 'news_default_expiry';
    public const _READ_MORE                      = 'read_more';
    public const _SAVE_FOR_LATER                 = 'save_for_later';
    public const _STAFF_CAN_POST_NEWS            = 'news_staff_post_enabled';
    public const _NEWS_EDIT_OTHERS               = 'news_edit_other';
    public const _BOARDER_LEAVE_ENABLED          = 'boarder_leave_enabled';
    public const _AUDIENCE_TO_FOLDER_TEACHERS    = 'audience_to_folder_users_teachers';
    public const _EXTERNAL_MS_SQL_SERVER_DRIVER  = 'external_ms_sql_server_driver';
    public const COMMS_COMMENTS_ENABLED          = 'comms_comments_enabled';
    public const NAME_EXTERNAL_TYPE              = 'external_type';
    public const NAME_ALLOW_STUDENT_CLASS_ACCESS = 'allow_student_class_access';

    public const PREFIX_CLOUDWORK_OAUTH             = 'cloudwork_oauth_';
    public const _CLOUDWORK_OAUTH_IDENTITY_PROVIDER = self::PREFIX_CLOUDWORK_OAUTH . 'identity_provider';
    public const _CLOUDWORK_OAUTH_CLIENT_ID         = self::PREFIX_CLOUDWORK_OAUTH . 'client_id';
    public const _CLOUDWORK_OAUTH_CLIENT_SECRET     = self::PREFIX_CLOUDWORK_OAUTH . 'client_secret';
    public const _CANVAS_DATA_API_KEY               = 'canvas_data_api_key';
    public const _CANVAS_DATA_API_SECRET            = 'canvas_data_api_secret';


    /**
     * @throws \InvalidArgumentException
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Config::class);

        $this->uses(self::COL_ID, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses(self::COL_NAME, self::TYPE_STRING);
        $this->uses(self::COL_VALUE, self::TYPE_STRING);
        $this->uses(self::COL_TITLE, self::TYPE_STRING);
        $this->uses(self::COL_DESCRIPTION, self::TYPE_STRING);
        $this->uses(self::COL_FIELD, self::TYPE_INTEGER);
        $this->uses(self::COL_OPTIONS, self::TYPE_STRING);
        $this->uses(self::COL_TYPE, self::TYPE_INTEGER);
        $this->uses(self::COL_EDITABLE, self::TYPE_BOOL_ENUM);
        $this->uses(self::COL_HIDDEN, self::TYPE_BOOL_ENUM);
        $this->uses(self::COL_REQUIRED, self::TYPE_BOOL_ENUM);
        $this->uses(
            self::COL_MODULE,
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            [self::MODULE_SETTINGS, self::MODULE_COMMS, self::MODULE_CUSTOM, self::MODULE_OAUTH, self::MODULE_SCHOOLDATA]
        );
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateCache']);
    }


    /**
     * Find all config options that are available within Schoolbox.
     *
     * @return array
     */
    public function findAllConfigs(): array
    {
        $configs = [];
        $query   = $this->getDatabase()->prepare("SELECT name, value, field_id FROM {$this->getTable()}");
        $query->execute();
        foreach ($query as $row) {
            $configs[$row['name']] = $this->hydrateValue((int)$row['field_id'], $row['value']);
        }
        return $configs;
    }


    /**
     * Find all available config types, as a scalar list.
     *
     * @return array|string[]
     */
    public function findTypeList(): array
    {
        // Don't include boarder leave configs if boarder leave is off
        $query = "
            SELECT DISTINCT lu_config_type.type
            FROM {$this->getTable()}
            INNER JOIN lu_config_type ON config.type_id  = lu_config_type.id
        ";
        $statement = $this->getDatabase()->query($query);
        $result    = [];
        foreach ($statement as $row) {
            $result[] = $row['type'];
        }
        return $result;
    }


    /**
     * Post save callback to ensure cache is updated
     *
     * @param Config $model
     * @return bool
     */
    protected function updateCache(Config $model): bool
    {
        // TODO consider updating cache of ConfigLoader?
        return true;
    }


    protected function getSelectString(): string
    {
        return "
          SELECT  {$this->getColumnString()}
          FROM {$this->getTable()}
          INNER JOIN lu_config_field lcf on config.field_id = lcf.id
          LEFT  JOIN lu_config_type  lct on config.type_id  = lct.id
        ";
    }


    /**
     * Light-weight hydration to respect boolean fields
     *
     * @param int   $fieldId
     * @param mixed $value
     * @return mixed
     */
    private function hydrateValue(int $fieldId, $value)
    {
        switch ($fieldId) {
            case self::FIELD_BOOLEAN:
            case self::FIELD_CHECKBOX:
                return $value === null ? null : (bool)$value;
            default:
        }
        return $value;
    }


    /**
     * Override notifications to silence them
     *
     * @param object $object
     * @param string $verb
     * @param null $previous
     * @return void
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        //Silence notifications
    }
}
