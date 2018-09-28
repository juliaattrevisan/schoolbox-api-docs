<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Config;

use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use PDO;


/**
 * Class ConfigMapper
 *
 * @package Schoolbox\Config
 * @method Config findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 * @method GenericCollection|Config[] findByColumn($columnName, $columnValue, $options = array())
 */
class ConfigMapper extends Mapper implements IConfigLoader
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
    public const MODULE_SETTINGS = 'settings';
    public const MODULE_COMMS    = 'comms';

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
    public const _GLOBAL_MODERATION     = 'global_moderation';
    public const _PUBLISH_DATE_OFFSET   = 'publish_date_offset';
    public const _EXPIRY_DATE_OFFSET    = 'news_default_expiry';
    public const _READ_MORE             = 'read_more';
    public const _SAVE_FOR_LATER        = 'save_for_later';
    public const _STAFF_CAN_POST_NEWS   = 'news_staff_post_enabled';
    public const _NEWS_EDIT_OTHERS      = 'news_edit_other';
    public const _BOARDER_LEAVE_ENABLED = 'boarder_leave_enabled';

    /** @var array */
    protected $cache = array();

    /** @var array */
    protected $consts;

    /**
     * This mapper has no database by default.
     * (It can be installed later?)
     */
    protected function setupDatabase()
    {
    }

    /**
     * This object IS a config loader, so the method for building it returns itself.
     *
     * @return $this
     */
    protected function buildConfigLoader()
    {
        // this line will possibly blow up in the Doctor's face
        return $this;
    }

    /**
     * Set up required constants.
     */
    protected function setup()
    {
        $w = realpath(__DIR__ . "/../../..");
        $this->consts = array(
            'siteRoot' => $w,
            'webRoot'  => "$w/www",
            'webBase'  => ''
        );
    }


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
        $this->uses(self::COL_MODULE, self::TYPE_STRING, self::COLUMN_ENUM, null, [self::MODULE_SETTINGS, self::MODULE_COMMS]);
    }


    /**
     * Get a config variable
     *
     * @param string $var
     * @return mixed
     */
    public function get($var)
    {
        if (isset($this->consts[$var])) {
            return $this->consts[$var];
        } elseif (defined($var)) {
            return constant($var);
        }
        if (empty($this->cache)) {
            $this->loadCache();
        }
        if (isset($this->cache[$var])) {
            return $this->cache[$var];
        }
        return null;
    }

    /**
     * Load entire cache at once, not just one result at a time
     */
    protected function loadCache()
    {
        // Load database on request only
        if (!($this->database instanceof \Database)) {
            $this->database = \Database::getInstance();
        }

        // Perform this common action without the overhead of hydration
        foreach ($this->database->query("SELECT `name`, `value`, field_id FROM `{$this->getTable()}`") as $row) {
            $value = $row['value'];
            switch ($row['field_id']) {
                case self::FIELD_BOOLEAN:
                case self::FIELD_CHECKBOX:
                    $value = (bool)$value;
                    break;
            }
            $this->cache[$row['name']] = $value;
        }
    }


    /**
     * Drop any cached config
     *
     * @return void
     */
    public function dropCache()
    {
        $this->cache = array();
    }


    /**
     * Returns all config items for a specific lu_config_type.type, and optional module
     *
     * @param string      $typeLabel
     * @param string|null $module
     * @return GenericCollection|Config[]
     */
    public function findByTypeLabel(string $typeLabel, string $module = null): GenericCollection
    {
        $pb = $this->getPredicateBuilder()
            ->equals('lct.type', $typeLabel);

        if ($module) {
            $pb->equals(self::COL_MODULE, $module);
        }

        if(!$this->get(self::_BOARDER_LEAVE_ENABLED)){
            $pb->notEquals(self::COL_TYPE, self::TYPE_BOARD);
        }

        return $this->findByPredicates($pb);
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
