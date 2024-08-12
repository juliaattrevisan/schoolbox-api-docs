<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Config;

use PDOStatement;
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
    public const MODULE_LTI        = 'lti';
    public const MODULE_REPORT_EXPORT = 'summative-report';
    public const MODULE_EPORTFOLIO = 'eportfolio';

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
    public const FIELD_SELECT_MULTI  = 17;
    public const FIELD_SELECT_DATE   = 18;
    public const FIELD_URI           = 19;
    public const FIELD_RANGE         = 20;

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
    public const _AUDIENCE_TO_FOLDER_TEACHERS    = 'audience_to_folder_users_teachers';
//    public const _EXTERNAL_MS_SQL_SERVER_DRIVER  = 'external_ms_sql_server_driver';
    public const COMMS_COMMENTS_ENABLED          = 'comms_comments_enabled';
    public const COMMS_STATISTICS_ENABLED        = 'comms_statistics_enabled';
    public const COMMS_STICKY_ENABLED            = 'comms_sticky_enabled';
    public const NAME_EXTERNAL_TYPE              = 'external_type';
    public const NAME_ALLOW_STUDENT_CLASS_ACCESS = 'allow_student_class_access';
    public const NAME_LMS_RESUBMIT_WINDOW        = 'lms_resubmit_window';

    public const PREFIX_CLOUDWORK_OAUTH                  = 'cloudwork_oauth_';
    public const _CLOUDWORK_OAUTH_IDENTITY_PROVIDER_TYPE = self::PREFIX_CLOUDWORK_OAUTH . 'identity_provider_type';
    public const _CLOUDWORK_OAUTH_IDENTITY_PROVIDER      = self::PREFIX_CLOUDWORK_OAUTH . 'identity_provider';
    public const _CLOUDWORK_OAUTH_CLIENT_ID              = self::PREFIX_CLOUDWORK_OAUTH . 'client_id';
    public const _CLOUDWORK_OAUTH_CLIENT_SECRET          = self::PREFIX_CLOUDWORK_OAUTH . 'client_secret';
    public const _CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS     = self::PREFIX_CLOUDWORK_OAUTH . 'identifying_claims';
    public const NAME_OAUTH_ISSUER                       = 'oauth_issuer';
    public const NAME_OAUTH_ENABLE_SSO                   = 'oauth_enable_sso';
    public const NAME_OAUTH_RESPONSE_TYPE                = 'oauth_response_type';
    public const NAME_OAUTH_SCOPES                       = 'oauth_scopes';

    public const _CANVAS_DATA_API_KEY    = 'canvas_data_api_key';
    public const _CANVAS_DATA_API_SECRET = 'canvas_data_api_secret';
    public const _CANVAS_URL             = 'canvas_url';
    public const _CANVAS_DATA2_CLIENT_ID = 'canvas_data2_client_id';
    public const _CANVAS_DATA2_SECRET    = 'canvas_data2_secret';

    public const NAME_OTFS_ADAPTER                   = 'otfs_adapter';
    public const NAME_OTFS_S3_BUCKET                 = 'otfs_s3_bucket';
    public const NAME_OTFS_VERIFY_PREVIOUS_FILE      = 'otfs_verify_previous_file';
    public const NAME_S3_PRESIGNED_URL_ENABLED       = 'otfs_s3_presigned_urls_enabled';
    public const NAME_IMAGE_S3_PRESIGNED_URL_ENABLED = 'otfs_image_s3_presigned_urls_enabled';
    public const NAME_OTFS_S3_CRED_KEY               = 'otfs_s3_cred_key';
    public const NAME_OTFS_S3_CRED_SECRET            = 'otfs_s3_cred_secret';
    public const NAME_OTFS_S3_REGION                 = 'otfs_s3_region';
    public const NAME_OTFS_S3_VERSION                = 'otfs_s3_version';

    public const NAME_MAIL_ENABLED           = 'mail_enable';
    public const NAME_MAIL_ADDRESS           = 'mail_address';
    public const NAME_EMAIL_SIGNATURE        = 'email_signature';
    public const NAME_MAIL_FORCE_FROM_SYSTEM = 'mail_force_from_system';
    public const NAME_MAIL_PASSWORD_CHANGE_FROM    = 'password_change_email_from';
    public const NAME_MAIL_PASSWORD_CHANGE_SUBJECT = 'password_change_email_subject';
    public const NAME_MAIL_SUPPORT_ADDRESS   = 'mail_support_address';
    public const NAME_MAIL_FROM_ADDRESS      = 'mail_from_address';
    public const NAME_MAIL_FROM_NAME         = 'mail_from_name';
    public const NAME_AUTH_EMAIL_ENABLED     = 'auth_email_enabled';

    public const NAME_BROWSER_CACHE_IMAGE_TIMEOUT    = 'browser_cache_image_timeout';
    public const NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT = 'browser_cache_portrait_timeout';

    public const NAME_UPCOMING_ASSESSMENTS_ENABLED = 'upcoming_assessments_enabled';
    public const NAME_GRADES_ENABLED               = 'grades_enabled';

    public const NAME_LEARNING_MOMENTS_ENABLED       = 'learning_moments_enabled';
    public const NAME_BADGES_ENABLED                 = 'badges_enabled';
    public const NAME_ACTIVITY_FEED_FEEDBACK_ENABLED = 'activity_feed_feedback_enabled';

    public const NAME_JWT_PUBLIC_KEY  = 'jwt_public_key';
    public const NAME_JWT_PRIVATE_KEY = 'jwt_private_key';
    public const NAME_JWT_LEEWAY      = 'jwt_leeway';
    public const NAME_QR_LOGIN_ENABLED = 'qr_login_enabled';
    public const NAME_ETHERPAD_HOST   = 'etherpad_host';

    public const NAME_SITE_PLAN = 'site_plan';
    public const NAME_SITE_VERSION = 'site_version';
    public const NAME_SITE_PACKAGE = 'site_package';
    public const NAME_SITE_DOMAIN = 'site_domain';
    public const NAME_SITE_SCHOOL_CODE = 'site_school_code';
    public const NAME_SITE_STATIC_ASSET_SERVE_FROM_CDN = 'site_static_asset_serve_from_cdn';
    public const NAME_SITE_STATIC_ASSET_PREFIX = 'site_static_asset_prefix';
    public const NAME_SITE_TYPE = 'site_type';
    public const NAME_SITE_ENVIRONMENT = 'site_environment';

    public const NAME_GOTENBERG_HOST = 'gotenberg_host';
    public const NAME_GOTENBERG_API_KEY = 'gotenberg_api_key';
    public const NAME_GOTENBERG_API_VERSION = 'gotenberg_api_version';
    public const NAME_SUMMATIVE_REPORT_ENABLED = 'summative_report_enabled';

    public const NAME_SHOW_SCHOOLBOX_HELP_LINK = 'show_schoolbox_help_link';

    public const NAME_EPORTFOLIO_ENABLED = 'eportfolio_enabled';
    public const NAME_EXTERNAL_MARKBOOK = 'external_markbook';
    public const NAME_CLASS_MARKBOOK_ENABLED = 'class_markbook_enable';
    public const NAME_COURSE_MARKBOOK_ENABLED = 'course_markbook_enable';
    public const NAME_RUBRIC_MARKBOOK_ENABLED = 'rubric_markbook_enable';
    public const NAME_PASTORAL_ENABLED = 'pastoral_enabled';
    public const NAME_NOTIFICATIONS_ENABLED = 'messaging_enabled';
    public const NAME_MOBILE_DAEMON_ENABLED = 'mobile_daemon_enabled';
    public const NAME_LMS_ENABLED = 'lms_enabled';
    public const NAME_RESTRICT_STUDENT_SUBMISSIONS = 'restrict_student_submissions';
    public const NAME_LTI_ASSESSMENTS_ENABLE = 'lti_assessments_enable';

    public const NAME_ASSESSMENT_CALENDAR_ENABLED = 'assessment_calendar_enabled';
    public const NAME_RESOURCE_BOOKING_ENABLED = 'resource_booking_enabled';
    public const NAME_FIXTURES_ENABLED = 'fixtures_enabled';
    public const NAME_TOURS_ENABLED = 'tours_enabled';
    public const NAME_EXTERNAL_DOCUMENT_REPORT_ENABLED = 'external_document_report_enabled';

    public const NAME_WEBDAV_ENABLED         = 'webdav_enable';
    public const NAME_GOOGLEDRIVE_ENABLED    = 'googledrive_enabled';
    public const NAME_GOOGLEDRIVE_EXPORT_PDF = 'googledrive_export_pdf';
    public const NAME_GOOGLEAPPS_API_KEY     = 'googleapps_api_key';
    public const NAME_GOOGLEAPPS_CLIENT_ID   = 'googleapps_client_id';
    public const NAME_MAX_FILE_SIZE = 'max_file_size';

    public const NAME_LMS_SEB_ENABLED = 'lms_seb_enable';

    public const NAME_INTELLISCHOOL_JWT_PRIVATE_KEY                   = 'intellischool_jwt_private_key';
    public const NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM = 'intellischool_jwt_private_key_signing_algorithm';
    public const NAME_INTELLISCHOOL_DEPLOYMENT_ID                     = 'intellischool_deployment_id';
    public const NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL        = 'intellischool_learner_dashboard_base_url';
    public const NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL         = 'intellischool_cohort_dashboard_base_url';

    public const NAME_PASTORAL_FLAG_COUNT_PERIOD = 'pastoral_flag_count_period';
    public const NAME_PASTORAL_SEVERITY_ENABLED = 'pastoral_severity';
    public const NAME_PASTORAL_ACTION_ENABLED = 'pastoral_action_enable';
    public const NAME_EXTERNAL_PASTORAL = 'external_pastoral';
    public const NAME_PASTORAL_EXTERNAL_DELETE = 'pastoral_external_delete';
    public const NAME_SHOW_ATTENDANCE_FLAGS = 'show_attendance_flags';
    public const NAME_PASTORAL_EXTERNAL_UPLOAD = 'external_pastoral_upload';

    public const NAME_PASTORAL_PARENT_ACCESS_ENABLED = 'pastoral_parent_access';
    public const NAME_PASTORAL_STUDENT_ACCESS_ENABLED = 'pastoral_student_access';

    public const NAME_REPORT_TITLE = 'report_title';

    public const NAME_REDIS_URL = 'redis_url';

    public const NAME_REDIS_EPHEMERAL_URL = 'redis_ephemeral_url';
    public const NAME_TASKD_REDIS_URL = 'taskd_redis_url';
    public const NAME_TASKD_REDIS_QUEUE = 'taskd_redis_queue';

    public const NAME_SCHOOLDATA_IMPORT_DATA_SOURCE = 'schooldata_import_data_source';

    public const NAME_EPORTFOLIO_SHOW_SUBJECTS_AND_GROUPS = 'eportfolio_show_subjects_and_groups';

    public const NAME_GROUP_STAFF_ONLY = 'group_staff_only';

    public const NAME_NAME_FORMAT = 'name_format';
    public const NAME_INSTANCE_REGISTRY_URL = 'instance_registry_url';
    public const NAME_START_OF_SCHOOL_YEAR  = 'start_of_school_year';

    public const NAME_DIGISTORM_APPLE_APP_STORE_LINK = 'digistorm_apple_app_store_link';
    public const NAME_DIGISTORM_GOOGLE_PLAY_STORE_LINK = 'digistorm_google_play_store_link';
    public const NAME_DIGISTORM_ROLE_INSTALL_PROMPT = 'digistorm_role_install_prompt';

    public const NAME_PAPERLY_API_HOSTNAME = 'paperly_iframe_api_host';
    public const NAME_PAPERLY_API_KEY = 'paperly_iframe_api_key';
    public const NAME_PAPERLY_AGENT_IP_WHITELIST = 'paperly_agent_ip_whitelist';
    public const NAME_PAPERLY_AGENT_SECRET = 'paperly_agent_secret';

    public const NAME_KAMI_ENABLED = 'kami_enabled';

    /* Kami Worksheets might not be available at v24 release
     * This feature flag is used to quietly hide this from users
     */
    public const NAME_KAMI_WORKSHEETS_ENABLED = 'kami_worksheets_enabled';

    public const NAME_KAMI_API_HOST = 'kami_api_host';
    public const NAME_KAMI_API_KEY = 'kami_api_key';

    /**
     * Components
     */
    public const NAME_IMAGE_SLIDESHOW_MAX = 'image_slideshow_max';

    /**
     * Skin Config Fields
     */
    public const NAME_HOMEPAGE_COLOUR_HEAD_FONT = 'homepage_colour_head_font';
    public const NAME_HOMEPAGE_COLOUR_HEAD_BG = 'homepage_colour_head_bg';
    public const NAME_HOMEPAGE_COLOUR_FONT = 'homepage_colour_font';
    public const NAME_HOMEPAGE_COLOUR_BG_PRIMARY = 'homepage_colour_bg_primary';
    public const NAME_SKIN_COLOUR_NAVIGATION_FOREGROUND = 'skin_colour_navigation_foreground';
    public const NAME_SKIN_COLOUR_NAVIGATION_BACKGROUND = 'skin_colour_navigation_background';
    public const NAME_SKIN_COLOUR_NAVIGATION_HOVER = 'skin_colour_navigation_hover';
    public const NAME_SKIN_COLOUR_OFFCANVAS_FOREGROUND = 'skin_colour_offcanvas_foreground';
    public const NAME_SKIN_COLOUR_OFFCANVAS_BACKGROUND = 'skin_colour_offcanvas_background';
    public const NAME_SKIN_COLOUR_OFFCANVAS_HOVER = 'skin_colour_offcanvas_hover';
    public const NAME_ACCOUNTS_MENU_BG_COLOUR = 'accounts_menu_bg_colour';
    public const NAME_SKIN_COLOUR_CONTENT_UI_FOREGROUND = 'skin_colour_content_ui_foreground';
    public const NAME_SKIN_COLOUR_CONTENT_UI_BACKGROUND = 'skin_colour_content_ui_background';
    public const NAME_SKIN_COLOUR_CONTENT_UI_HOVER = 'skin_colour_content_ui_hover';
    public const NAME_SKIN_COLOUR_CONTENT_UI_SELECTED = 'skin_colour_content_ui_selected';
    public const NAME_SKIN_COLOUR_CONTENT_UI_SUBMIT_FOREGROUND = 'skin_colour_content_ui_submit_foreground';
    public const NAME_SKIN_COLOUR_CONTENT_UI_SUBMIT_BACKGROUND = 'skin_colour_content_ui_submit_background';
    public const NAME_SKIN_COLOUR_ACCENT_FOREGROUND = 'skin_colour_accent_foreground';
    public const NAME_SKIN_COLOUR_ACCENT_BACKGROUND = 'skin_colour_accent_background';
    public const NAME_SKIN_COLOUR_ACCENT_HOVER = 'skin_colour_accent_hover';
    public const NAME_SKIN_COLOUR_LOGO_BACKGROUND = 'skin_colour_logo_background';
    public const NAME_SKIN_COLOUR_BODY_BG = 'skin_colour_body_bg';
    public const NAME_SKIN_LOGO_SQUARE = 'skin_logo_square';
    public const NAME_SKIN_LOGO_LARGE = 'skin_logo_large';
    public const NAME_SKIN_LOGO_LOGIN = 'skin_logo_login';
    public const NAME_SKIN_LOGIN_BG = 'skin_login_bg';
    public const NAME_SKIN_CUSTOM_CSS = 'skin_custom_css';
    public const NAME_SKIN_COLOUR_LOGIN_BACKGROUND = 'skin_colour_login_background';
    public const NAME_SKIN_COLOUR_FORM_INPUT_BORDER_COLOR = 'skin_colour_form_input_border_color';
    public const NAME_SKIN_COLOUR_FORM_BACKGROUND_HOVER_COLOR = 'skin_colour_form_background_hover_color';
    public const NAME_ACCOUNTS_MENU_TEXT_COLOUR = 'accounts_menu_text_colour';
    public const NAME_ACCOUNTS_MENU_HOVER_COLOUR = 'accounts_menu_hover_colour';
    public const NAME_EMAIL_BANNER_IMAGE = 'email_banner_image';
    public const NAME_SKIN_FAVICON = 'skin_favicon';

    /* Directories
     */
    public const NAME_REPORT_STAFF_ACCESS = 'report_staff_access';
    public const NAME_REPORT_STAFF_DIRECTORY_ENABLED = 'staff_directory_enabled';
    public const NAME_BACKUP_DIR = 'backup_dir';

    /* Maintenance mode
     */
    public const NAME_MAINTENANCE_MODE = 'maintenance_mode';

    /**
     * Isams specific
     */
    public const EXTERNAL_ISAMS_REST_API_HOST = 'external_isams_rest_api_host';
    public const EXTERNAL_ISAMS_REST_API_CLIENT_ID = 'external_isams_rest_api_client_id';
    public const EXTERNAL_ISAMS_REST_API_CLIENT_SECRET = 'external_isams_rest_api_client_secret';
    public const EXTERNAL_ISAMS_STUDENT_REGISTERS = 'external_isams_student_registers';
    public const EXTERNAL_ISAMS_FINANCIAL_REGISTERS = 'external_isams_financial_registers';

    /**
     * Config settings which may affect the behaviour of the router daemon.
     *
     * If any of these settings change: send a reload message to the router
     * daemon.
     */
    public const SETTINGS_AFFECTING_ROUTED = [
        'messaging_enabled',
        'mail_replyto_enabled',
        'feedback_url',
        'feedback_text',
        self::NAME_MAIL_FORCE_FROM_SYSTEM,
        self::NAME_MAIL_ENABLED
    ];

    /**
     * Config settings which may affect the behaviour of the schedule daemon.
     *
     * If any of these settings change: send a refresh configuration message
     * to the schedule daemon.
     */
    public const SETTINGS_AFFECTING_SCHEDULED = [
        'restrict_parent_duework_access',
        'restrict_student_submissions',
        'restrict_parent_grades',
        self::NAME_MOBILE_DAEMON_ENABLED,
    ];

    /**
     * Config settings which affect the modules and routes available to this
     * instance.
     *
     * If any of these settings change: rebuild the route and module tables.
     */
    public const SETTINGS_AFFECTING_MODULES    = [
        self::NAME_LMS_ENABLED,
        self::NAME_PASTORAL_ENABLED,
        self::NAME_LEARNING_MOMENTS_ENABLED,
        self::NAME_BADGES_ENABLED,
        self::NAME_ASSESSMENT_CALENDAR_ENABLED,
        self::NAME_RESOURCE_BOOKING_ENABLED,
        self::NAME_FIXTURES_ENABLED,
        self::NAME_TOURS_ENABLED,
        self::NAME_EXTERNAL_DOCUMENT_REPORT_ENABLED,
        self::NAME_EXTERNAL_TYPE,
        self::NAME_SCHOOLDATA_IMPORT_DATA_SOURCE,
        self::NAME_QR_LOGIN_ENABLED,
        self::NAME_SUMMATIVE_REPORT_ENABLED,
        self::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER,
        self::NAME_OAUTH_ENABLE_SSO,
        self::NAME_MAIL_ENABLED,
    ];

    /**
     * Config settings which affect the oAuth/OIDC provider.
     *
     * If any of these settings change: invalidate the OIDC cache.
     */
    public const SETTINGS_AFFECTING_OAUTH = [
        self::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER,
        self::_CLOUDWORK_OAUTH_CLIENT_ID,
        self::_CLOUDWORK_OAUTH_CLIENT_SECRET,
        self::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS,
        self::NAME_OAUTH_ISSUER,
        self::NAME_OAUTH_ENABLE_SSO,
        //        self::NAME_OAUTH_SSO_IP_BLACKLIST,
        //        self::NAME_OAUTH_SSO_IP_WHITELIST,
    ];

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
        //$this->uses(self::COL_TYPE, self::TYPE_INTEGER);
        $this->uses(self::COL_EDITABLE, self::TYPE_BOOL_ENUM);
        $this->uses(self::COL_HIDDEN, self::TYPE_BOOL_ENUM);
        $this->uses(self::COL_REQUIRED, self::TYPE_BOOL_ENUM);
        $this->uses(
            self::COL_MODULE,
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            [self::MODULE_SETTINGS, self::MODULE_COMMS, self::MODULE_CUSTOM, self::MODULE_OAUTH, self::MODULE_SCHOOLDATA, self::MODULE_LTI, self::MODULE_REPORT_EXPORT]
        );
        $this->joins(self::COL_TYPE, Type\Mapper::class, 'id', self::TYPE_MODEL, null, 'type', 'type');
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateCache']);
    }


    /**
     * Find all config options that are available within Schoolbox.
     *
     * @return array
     */
    public function findAllConfigs(): array
    {
        return (new DataSource())->findAllConfigs($this->database);
    }


    /**
     * Find all available config types, as a scalar list.
     *
     * @return array|string[]
     */
    public function findTypeList(): array
    {
        $query = "
            SELECT DISTINCT lu_config_type.type
            FROM {$this->getTable()}
            INNER JOIN lu_config_type ON config.type_id  = lu_config_type.id
            WHERE hidden = 'false'
            ORDER BY lu_config_type.sequence
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
          SELECT  {$this->getColumnString()}, {$this->getJoinColumnString('type', 'lct')}
          FROM {$this->getTable()}
          INNER JOIN lu_config_field lcf on config.field_id = lcf.id
          LEFT  JOIN lu_config_type  lct on config.type_id  = lct.id
        ";
    }

    protected function hydrateSelect(PDOStatement $statement)
    {
        $configs = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $config = $this->hydrate($row);
            $type = $this->hydrateJoin($row, 'type', 'lct');
            if ($type instanceof Type\Model) {
                $config->setType($type);
            }
            $configs->add($config);
        }
        return $configs;
    }

    /**
     * Override notifications to silence them
     *
     * TODO: consider using system-level notifications to reload
     *       routed/scheduled/modules
     * (Currently, reloading of daemons and modules is handled in
     *  `\Schoolbox\Admin\Settings\Controller::postSettings`)
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
