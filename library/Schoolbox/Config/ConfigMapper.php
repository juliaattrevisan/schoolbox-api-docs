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
    public const string TABLE_NAME = 'config';

    public const string COL_ID          = 'configID';
    public const string COL_NAME        = 'name';
    public const string COL_VALUE       = 'value';
    public const string COL_TITLE       = 'title';
    public const string COL_DESCRIPTION = 'description';
    public const string COL_FIELD       = 'field_id';
    public const string COL_OPTIONS     = 'options';
    public const string COL_TYPE        = 'type_id';
    public const string COL_EDITABLE    = 'editable';
    public const string COL_HIDDEN      = 'hidden';
    public const string COL_REQUIRED    = 'required';
    public const string COL_MODULE      = 'module';

    // Module column enum
    public const string MODULE_SETTINGS   = 'settings';
    public const string MODULE_COMMS      = 'comms';
    public const string MODULE_CUSTOM     = 'custom';
    public const string MODULE_OAUTH      = 'oauth';
    public const string MODULE_SCHOOLDATA = 'schoolData';
    public const string MODULE_LTI        = 'lti';
    public const string MODULE_REPORT_EXPORT = 'summative-report';
    public const string MODULE_FEATURE_TOURS = 'feature-tours';
    public const string MODULE_AI_SERVICES = 'ai-services';
    public const string MODULE_DIFFERENTIATION_PROFILES = 'differentiation-profiles';

    // Field enum (kinda)
    public const int FIELD_INPUT         =  1;
    public const int FIELD_TEXT_AREA     =  2;
    public const int FIELD_BOOLEAN       =  3;
    public const int FIELD_SELECT        =  4;
    public const int FIELD_CHECKBOX      =  5;
    public const int FIELD_BYTES         =  6;
    public const int FIELD_SELECT_KEY    =  7;
    public const int FIELD_HTML          =  8;
    public const int FIELD_RESOURCE      =  9;
    public const int FIELD_SELECT_LOOKUP = 10;
    public const int FIELD_COLOUR        = 11;
    public const int FIELD_FILE          = 12;
    public const int FIELD_FILE_MULTI    = 13;
    public const int FIELD_PASSWORD      = 14;
    public const int FIELD_TIME_PICKER   = 15;
    public const int FIELD_NUMBER        = 16;
    public const int FIELD_SELECT_MULTI  = 17;
    public const int FIELD_SELECT_DATE   = 18;
    public const int FIELD_URI           = 19;
    public const int FIELD_RANGE         = 20;
    public const int FIELD_JSON          = 21;

    // Type enum
    public const int TYPE_SITE          =  1;
    public const int TYPE_SECURITY      =  2;
    public const int TYPE_PATHS         =  3;
    public const int TYPE_RESOURCES     =  4;
    public const int TYPE_POLICY        =  5;
    public const int TYPE_LOCALISATION  =  6;
    public const int TYPE_EXTERNAL      =  7;
    public const int TYPE_EMAIL         =  8;
    public const int TYPE_COLOURS       =  9;
    public const int TYPE_3RD           = 10;
    public const int TYPE_MODULES       = 11;
    public const int TYPE_WELCOME       = 13;
    public const int TYPE_DEBUGGING     = 14;
    public const int TYPE_LMS_POLICY    = 15;
    public const int TYPE_LDAP          = 16;
    public const int TYPE_PROXY         = 17;
    public const int TYPE_SKIN_CONFIG   = 18;
    public const int TYPE_PASTORAL_CARE = 19;
    public const int TYPE_DOC_MANAGE    = 20;
    public const int TYPE_SAML          = 21;
    public const int TYPE_AI            = 22;
    public const int TYPE_CANVAS        = 23;
    public const int TYPE_DATA_RETENTION = 25;

    // Technically these should be prefixed with 'NAME_', but it gets a bit verbose for little extra clarity
    public const string _GLOBAL_MODERATION              = 'global_moderation';
    public const string _PUBLISH_DATE_OFFSET            = 'publish_date_offset';
    public const string _EXPIRY_DATE_OFFSET             = 'news_default_expiry';
    public const string _READ_MORE                      = 'read_more';
    public const string _SAVE_FOR_LATER                 = 'save_for_later';
    public const string _STAFF_CAN_POST_NEWS            = 'news_staff_post_enabled';
    public const string _AUDIENCE_TO_FOLDER_TEACHERS    = 'audience_to_folder_users_teachers';
    //    public const string _EXTERNAL_MS_SQL_SERVER_DRIVER  = 'external_ms_sql_server_driver';
    public const string COMMS_COMMENTS_ENABLED          = 'comms_comments_enabled';
    public const string COMMS_STATISTICS_ENABLED        = 'comms_statistics_enabled';
    public const string COMMS_STICKY_ENABLED            = 'comms_sticky_enabled';
    public const string NAME_EXTERNAL_TYPE              = 'external_type';
    public const string NAME_EXTERNAL_DB_HOST           = 'external_db_host';
    public const string NAME_EXTERNAL_DB_USER           = 'external_db_user';
    public const string NAME_EXTERNAL_DB_PASSWORD       = 'external_db_passwd';
    public const string NAME_EXTERNAL_DB_NAME           = 'external_db_db';
    public const string NAME_EXTERNAL_DB_ENCRYPTION     = 'external_db_encryption';

    public const string NAME_ALLOW_STUDENT_CLASS_ACCESS = 'allow_student_class_access';
    public const string NAME_LMS_RESUBMIT_WINDOW        = 'lms_resubmit_window';

    public const string PREFIX_CLOUDWORK_OAUTH                  = 'cloudwork_oauth_';
    public const string _CLOUDWORK_OAUTH_IDENTITY_PROVIDER_TYPE = self::PREFIX_CLOUDWORK_OAUTH . 'identity_provider_type';
    public const string _CLOUDWORK_OAUTH_IDENTITY_PROVIDER      = self::PREFIX_CLOUDWORK_OAUTH . 'identity_provider';
    public const string _CLOUDWORK_OAUTH_CLIENT_ID              = self::PREFIX_CLOUDWORK_OAUTH . 'client_id';
    public const string _CLOUDWORK_OAUTH_CLIENT_SECRET          = self::PREFIX_CLOUDWORK_OAUTH . 'client_secret';
    public const string _CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS     = self::PREFIX_CLOUDWORK_OAUTH . 'identifying_claims';
    public const string NAME_OAUTH_ISSUER                       = 'oauth_issuer';
    public const string NAME_OAUTH_ENABLE_SSO                   = 'oauth_enable_sso';
    public const string NAME_OAUTH_RESPONSE_TYPE                = 'oauth_response_type';
    public const string NAME_OAUTH_SCOPES                       = 'oauth_scopes';
    public const string NAME_OAUTH_DEBUG_MODE                   = 'oauth_debug_mode';

    // Canvas Settings
    public const string NAME_CANVAS_ENABLED     = 'canvas_enabled';
    public const string _CANVAS_DATA_API_KEY    = 'canvas_data_api_key';
    public const string _CANVAS_DATA_API_SECRET = 'canvas_data_api_secret';
    public const string _CANVAS_URL             = 'canvas_url';
    public const string _CANVAS_DATA2_CLIENT_ID = 'canvas_data2_client_id';
    public const string _CANVAS_DATA2_SECRET    = 'canvas_data2_secret';

    public const string NAME_CANVAS_IMPORT_QUIZ = 'canvas_import_quiz';
    public const string NAME_CANVAS_QUIZ_WORKTYPE = 'canvas_quiz_worktype';
    public const string NAME_CANVAS_IMPORT_GRADES = 'canvas_import_grades';
    public const string NAME_CANVAS_GRADE_GROUPS = 'canvas_grade_groups';
    public const string NAME_CANVAS_SISSOURCEID_TEMPLATE = 'canvas_sissourceid_template';
    public const string NAME_CANVAS_SYNC_NEWS = 'canvas_sync_news';
    public const string NAME_CANVAS_NEWS_TOPIC = 'canvas_news_topic';
    public const string NAME_CANVAS_NEWS_AUTHOR = 'canvas_news_author';
    public const string NAME_CANVAS_REDIRECT_ASSESSMENTS = 'canvas_redirect_assessments';
    public const string NAME_CANVAS_REDIRECT_HOMEPAGES = 'canvas_redirect_homepages';
    public const string NAME_CANVAS_CODE_REDIRECT_HOMEPAGES = 'canvas_code_redirect_homepages';


    public const string NAME_OTFS_ADAPTER                   = 'otfs_adapter';
    public const string NAME_OTFS_S3_BUCKET                 = 'otfs_s3_bucket';
    public const string NAME_OTFS_VERIFY_PREVIOUS_FILE      = 'otfs_verify_previous_file';
    public const string NAME_S3_PRESIGNED_URL_ENABLED       = 'otfs_s3_presigned_urls_enabled';
    public const string NAME_IMAGE_S3_PRESIGNED_URL_ENABLED = 'otfs_image_s3_presigned_urls_enabled';
    public const string NAME_OTFS_S3_CRED_KEY               = 'otfs_s3_cred_key';
    public const string NAME_OTFS_S3_CRED_SECRET            = 'otfs_s3_cred_secret';
    public const string NAME_OTFS_S3_REGION                 = 'otfs_s3_region';
    public const string NAME_OTFS_S3_VERSION                = 'otfs_s3_version';
    public const string NAME_OTFS_S3_ALT_HOST               = 'otfs_s3_alt_host';
    public const string NAME_OTFS_S3_ALT_ACCESS_KEY         = 'otfs_s3_alt_access_key';
    public const string NAME_OTFS_S3_ALT_SECRET_KEY         = 'otfs_s3_alt_secret_key';
    public const string NAME_OTFS_S3_ALT_BUCKET             = 'otfs_s3_alt_bucket';
    public const string NAME_OTFS_S3_ALT_REGION             = 'otfs_s3_alt_region';

    public const string NAME_MAIL_ENABLED           = 'mail_enable';
    public const string NAME_MAIL_ADDRESS           = 'mail_address';
    public const string NAME_EMAIL_SIGNATURE        = 'email_signature';
    public const string NAME_MAIL_FORCE_FROM_SYSTEM = 'mail_force_from_system';
    public const string NAME_MAIL_PASSWORD_CHANGE_FROM    = 'password_change_email_from';
    public const string NAME_MAIL_PASSWORD_CHANGE_SUBJECT = 'password_change_email_subject';
    public const string NAME_MAIL_SUPPORT_ADDRESS   = 'mail_support_address';
    public const string NAME_MAIL_FROM_ADDRESS      = 'mail_from_address';
    public const string NAME_MAIL_FROM_NAME         = 'mail_from_name';

    public const string AMAZON_SES_USERNAME         = 'amazon_ses_username';

    public const string AMAZON_SES_PASSWORD         = 'amazon_ses_password';

    public const string AMAZON_SES_REGION         = 'amazon_ses_region';
    public const string NAME_AUTH_EMAIL_ENABLED     = 'auth_email_enabled';

    public const string NAME_BROWSER_CACHE_IMAGE_TIMEOUT    = 'browser_cache_image_timeout';
    public const string NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT = 'browser_cache_portrait_timeout';

    public const string NAME_XML_CACHE_TIMEOUT = 'xml_cache_timeout';

    public const string NAME_UPCOMING_ASSESSMENTS_ENABLED = 'upcoming_assessments_enabled';
    public const string NAME_GRADES_ENABLED               = 'grades_enabled';
    public const string NAME_GRADE_GRADIENT_ENABLED       = 'grade_gradient_enable';

    public const string NAME_LEARNING_MOMENTS_ENABLED       = 'learning_moments_enabled';
    public const string NAME_LEARNING_MOMENTS_MODERATION_ENABLED    = 'learning_moments_moderation_enabled';

    public const string NAME_BADGES_ENABLED                 = 'badges_enabled';
    public const string NAME_ACTIVITY_FEED_FEEDBACK_ENABLED = 'activity_feed_feedback_enabled';

    public const string NAME_JWT_PUBLIC_KEY  = 'jwt_public_key';
    public const string NAME_JWT_PRIVATE_KEY = 'jwt_private_key';
    public const string NAME_JWT_LEEWAY      = 'jwt_leeway';
    public const string NAME_QR_LOGIN_ENABLED = 'qr_login_enabled';
    public const string NAME_ETHERPAD_HOST   = 'etherpad_host';

    public const string NAME_SITE_PLAN = 'site_plan';
    public const string NAME_SITE_VERSION = 'site_version';
    public const string NAME_SITE_PACKAGE = 'site_package';
    public const string NAME_SITE_HOSTNAME = 'site_hostname';
    public const string NAME_SITE_DOMAIN = 'site_domain';
    public const string NAME_SITE_SCHOOL_CODE = 'site_school_code';
    public const string NAME_SITE_STATIC_ASSET_SERVE_FROM_CDN = 'site_static_asset_serve_from_cdn';
    public const string NAME_SITE_STATIC_ASSET_PREFIX = 'site_static_asset_prefix';
    public const string NAME_SITE_TYPE = 'site_type';
    public const string NAME_SITE_ENVIRONMENT = 'site_environment';

    public const string NAME_GOTENBERG_HOST = 'gotenberg_host';
    public const string NAME_GOTENBERG_API_KEY = 'gotenberg_api_key';
    public const string NAME_SUMMATIVE_REPORT_ENABLED = 'summative_report_enabled';

    public const string NAME_CURRICULUM_COVERAGE_REPORT_ENABLED = 'curriculum_coverage_report_enabled';

    public const string NAME_PWA_ENABLED = 'pwa_enable';
    public const string NAME_WEB_PUSH_NOTIFICATIONS_ENABLED = 'web_push_notifications_enabled';

    public const string NAME_SHOW_SCHOOLBOX_HELP_LINK = 'show_schoolbox_help_link';

    public const string NAME_EPORTFOLIO_ENABLED = 'eportfolio_enabled';
    public const string NAME_EXTERNAL_MARKBOOK = 'external_markbook';
    public const string NAME_CLASS_MARKBOOK_ENABLED = 'class_markbook_enable';
    public const string NAME_COURSE_MARKBOOK_ENABLED = 'course_markbook_enable';
    public const string NAME_RUBRIC_MARKBOOK_ENABLED = 'rubric_markbook_enable';
    public const string NAME_PASTORAL_ENABLED = 'pastoral_enabled';
    public const string NAME_NOTIFICATIONS_ENABLED = 'messaging_enabled';
    public const string NAME_MOBILE_DAEMON_ENABLED = 'mobile_daemon_enabled';
    public const string NAME_LMS_ENABLED = 'lms_enabled';
    public const string NAME_RESTRICT_STUDENT_SUBMISSIONS = 'restrict_student_submissions';

    public const string NAME_SUBMISSION_VISIBILITY_MAX = 'submission_visibility_max';
    public const string NAME_LTI_ASSESSMENTS_ENABLE = 'lti_assessments_enable';
    public const string NAME_LTI_DEBUG_MODE         = 'lti_debug_mode';

    public const string NAME_CUSTOM_ASSESSMENT_PARTICIPATION = 'custom_assessment_participation';
    public const string NAME_ASSESSMENT_CALENDAR_ENABLED = 'assessment_calendar_enabled';
    public const string NAME_RESOURCE_BOOKING_ENABLED = 'resource_booking_enabled';
    public const string NAME_FIXTURES_ENABLED = 'fixtures_enabled';
    public const string NAME_TOURS_ENABLED = 'tours_enabled';
    public const string NAME_EXTERNAL_DOCUMENT_REPORT_ENABLED = 'external_document_report_enabled';

    public const string NAME_WEBDAV_ENABLED         = 'webdav_enable';
    public const string NAME_GOOGLEDRIVE_ENABLED    = 'googledrive_enabled';
    public const string NAME_GOOGLEDRIVE_EXPORT_PDF = 'googledrive_export_pdf';
    public const string NAME_GOOGLEAPPS_API_KEY     = 'googleapps_api_key';
    public const string NAME_GOOGLEAPPS_CLIENT_ID   = 'googleapps_client_id';
    public const string NAME_MAX_FILE_SIZE = 'max_file_size';

    public const string NAME_RUBRIC_MAX_LEVELS = 'rubric_max_levels';

    public const string NAME_LMS_SEB_ENABLED = 'lms_seb_enable';

    public const string NAME_INTELLISCHOOL_JWT_PRIVATE_KEY                   = 'intellischool_jwt_private_key';
    public const string NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM = 'intellischool_jwt_private_key_signing_algorithm';
    public const string NAME_INTELLISCHOOL_DEPLOYMENT_ID                     = 'intellischool_deployment_id';
    public const string NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL        = 'intellischool_learner_dashboard_base_url';
    public const string NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL         = 'intellischool_cohort_dashboard_base_url';

    public const string NAME_PASTORAL_SEVERITY_ENABLED = 'pastoral_severity';
    public const string NAME_PASTORAL_ACTION_ENABLED = 'pastoral_action_enable';
    public const string NAME_EXTERNAL_PASTORAL = 'external_pastoral';
    public const string NAME_PASTORAL_EXTERNAL_DELETE = 'pastoral_external_delete';
    public const string NAME_SHOW_ATTENDANCE_FLAGS = 'show_attendance_flags';
    public const string NAME_PASTORAL_EXTERNAL_UPLOAD = 'external_pastoral_upload';

    public const string NAME_PASTORAL_INDIVIDUAL_ACCESS_ENABLED = 'pastoral_individual_access';
    public const string NAME_PASTORAL_PARENT_ACCESS_ENABLED = 'pastoral_parent_access';
    public const string NAME_PASTORAL_STUDENT_ACCESS_ENABLED = 'pastoral_student_access';
    public const string NAME_PASTORAL_BULK_IMPORT_ENABLED    = 'pastoral_bulk_import_enabled';
    public const string NAME_PASTORAL_POINT_ENABLED    = 'pastoral_point_enabled';
    public const string NAME_PASTORAL_DEFAULT_POINTS_ENABLED    = 'pastoral_default_points_enabled';

    public const string NAME_REPORT_TITLE = 'report_title';

    public const string NAME_REDIS_URL = 'redis_url';

    public const string NAME_REDIS_EPHEMERAL_URL = 'redis_ephemeral_url';
    public const string NAME_TASKD_REDIS_URL = 'taskd_redis_url';
    public const string NAME_TASKD_REDIS_QUEUE = 'taskd_redis_queue';

    public const string NAME_SCHOOLDATA_IMPORT_DATA_SOURCE = 'schooldata_import_data_source';

    public const string NAME_EPORTFOLIO_SHOW_SUBJECTS_AND_GROUPS = 'eportfolio_show_subjects_and_groups';

    public const string NAME_GROUP_STAFF_ONLY = 'group_staff_only';

    public const string NAME_NAME_FORMAT = 'name_format';
    public const string NAME_INSTANCE_REGISTRY_URL = 'instance_registry_url';
    public const string NAME_START_OF_SCHOOL_YEAR  = 'start_of_school_year';

    public const string NAME_DIGISTORM_APPLE_APP_STORE_LINK = 'digistorm_apple_app_store_link';
    public const string NAME_DIGISTORM_GOOGLE_PLAY_STORE_LINK = 'digistorm_google_play_store_link';
    public const string NAME_DIGISTORM_ROLE_INSTALL_PROMPT = 'digistorm_role_install_prompt';

    public const string NAME_PAPERLY_API_HOSTNAME = 'paperly_iframe_api_host';
    public const string NAME_PAPERLY_API_KEY = 'paperly_iframe_api_key';
    public const string NAME_PAPERLY_AGENT_IP_WHITELIST = 'paperly_agent_ip_whitelist';
    public const string NAME_PAPERLY_AGENT_SECRET = 'paperly_agent_secret';
    public const string NAME_PAPERLY_AGENT_EXTERNAL_DB_HOST_OVERRIDE = 'paperly_agent_external_db_host_override';
    public const string NAME_PAPERLY_AGENT_EXTERNAL_DB_USER_OVERRIDE = 'paperly_agent_external_db_user_override';
    public const string NAME_PAPERLY_AGENT_EXTERNAL_DB_PASSWORD_OVERRIDE = 'paperly_agent_external_db_password_override';
    public const string NAME_PAPERLY_AGENT_EXTERNAL_DB_NAME_OVERRIDE = 'paperly_agent_external_db_name_override';
    public const string NAME_PAPERLY_OPEN_IN_NEW_TAB = 'paperly_open_in_new_tab';


    # GeoSnapShot settings
    public const string NAME_GEOSNAPSHOT_ENABLED     = 'geosnapshot_enabled'; // Feature flag
    public const string NAME_GEOSNAPSHOT_API_KEY     = 'geosnapshot_api_key';
    public const string NAME_GEOSNAPSHOT_LAUNCH_URL  = 'geosnapshot_launch_url'; // oAuth Login URL (unique to all schools)
    public const string NAME_GEOSNAPSHOT_LIBRARY_URL = 'geosnapshot_library_url'; // Management URL for staff (common to all schools)


    public const string NAME_KAMI_ENABLED = 'kami_enabled';

    /* Kami Worksheets might not be available at v24 release
     * This feature flag is used to quietly hide this from users
     */
    public const string NAME_KAMI_WORKSHEETS_ENABLED = 'kami_worksheets_enabled';

    public const string NAME_KAMI_API_HOST = 'kami_api_host';
    public const string NAME_KAMI_API_KEY = 'kami_api_key';

    /**
     * Components
     */
    public const string NAME_IMAGE_SLIDESHOW_MAX = 'image_slideshow_max';

    /**
     * Advanced Search
     */
    public const string NAME_ADVANCED_SEARCH_RESULT_PAGE_SIZE = 'advanced_search_result_page_size';
    public const string NAME_ADVANCED_SEARCH_RESULT_PAGES_PER_BATCH = 'advanced_search_result_pages_per_batch';

    /**
     * Skin Config Fields
     */
    public const string NAME_HOMEPAGE_COLOUR_HEAD_FONT = 'homepage_colour_head_font';
    public const string NAME_HOMEPAGE_COLOUR_HEAD_BG = 'homepage_colour_head_bg';
    public const string NAME_HOMEPAGE_COLOUR_FONT = 'homepage_colour_font';
    public const string NAME_HOMEPAGE_COLOUR_BG_PRIMARY = 'homepage_colour_bg_primary';
    public const string NAME_SKIN_COLOUR_NAVIGATION_FOREGROUND = 'skin_colour_navigation_foreground';
    public const string NAME_SKIN_COLOUR_NAVIGATION_BACKGROUND = 'skin_colour_navigation_background';
    public const string NAME_SKIN_COLOUR_NAVIGATION_HOVER = 'skin_colour_navigation_hover';
    public const string NAME_SKIN_COLOUR_OFFCANVAS_FOREGROUND = 'skin_colour_offcanvas_foreground';
    public const string NAME_SKIN_COLOUR_OFFCANVAS_BACKGROUND = 'skin_colour_offcanvas_background';
    public const string NAME_SKIN_COLOUR_OFFCANVAS_HOVER = 'skin_colour_offcanvas_hover';
    public const string NAME_ACCOUNTS_MENU_BG_COLOUR = 'accounts_menu_bg_colour';
    public const string NAME_SKIN_COLOUR_CONTENT_UI_FOREGROUND = 'skin_colour_content_ui_foreground';
    public const string NAME_SKIN_COLOUR_CONTENT_UI_BACKGROUND = 'skin_colour_content_ui_background';
    public const string NAME_SKIN_COLOUR_CONTENT_UI_HOVER = 'skin_colour_content_ui_hover';
    public const string NAME_SKIN_COLOUR_CONTENT_UI_SELECTED = 'skin_colour_content_ui_selected';
    public const string NAME_SKIN_COLOUR_CONTENT_UI_SUBMIT_FOREGROUND = 'skin_colour_content_ui_submit_foreground';
    public const string NAME_SKIN_COLOUR_CONTENT_UI_SUBMIT_BACKGROUND = 'skin_colour_content_ui_submit_background';
    public const string NAME_SKIN_COLOUR_ACCENT_FOREGROUND = 'skin_colour_accent_foreground';
    public const string NAME_SKIN_COLOUR_ACCENT_BACKGROUND = 'skin_colour_accent_background';
    public const string NAME_SKIN_COLOUR_ACCENT_HOVER = 'skin_colour_accent_hover';
    public const string NAME_SKIN_COLOUR_LOGO_BACKGROUND = 'skin_colour_logo_background';
    public const string NAME_SKIN_COLOUR_BODY_BG = 'skin_colour_body_bg';
    public const string NAME_SKIN_LOGO_SQUARE = 'skin_logo_square';
    public const string NAME_SKIN_LOGO_LARGE = 'skin_logo_large';
    public const string NAME_SKIN_LOGO_LOGIN = 'skin_logo_login';
    public const string NAME_SKIN_LOGIN_BG = 'skin_login_bg';
    public const string NAME_SKIN_CUSTOM_CSS = 'skin_custom_css';
    public const string NAME_SKIN_COLOUR_LOGIN_BACKGROUND = 'skin_colour_login_background';
    public const string NAME_SKIN_COLOUR_FORM_INPUT_BORDER_COLOR = 'skin_colour_form_input_border_color';
    public const string NAME_SKIN_COLOUR_FORM_BACKGROUND_HOVER_COLOR = 'skin_colour_form_background_hover_color';
    public const string NAME_ACCOUNTS_MENU_TEXT_COLOUR = 'accounts_menu_text_colour';
    public const string NAME_ACCOUNTS_MENU_HOVER_COLOUR = 'accounts_menu_hover_colour';
    public const string NAME_EMAIL_BANNER_IMAGE = 'email_banner_image';
    public const string NAME_SKIN_FAVICON = 'skin_favicon';

    /* Directories
     */
    public const string NAME_REPORT_STAFF_ACCESS = 'report_staff_access';
    public const string NAME_REPORT_STAFF_DIRECTORY_ENABLED = 'staff_directory_enabled';
    public const string NAME_BACKUP_DIR = 'backup_dir';

    /* Maintenance mode
     */
    public const string NAME_MAINTENANCE_MODE = 'maintenance_mode';

    /* Profile Editor name formats
     */
    public const string NAME_NAME_FORMAT_STUDENT = 'name_format_student';
    public const string NAME_NAME_FORMAT_STAFF = 'name_format_staff';
    public const string NAME_NAME_FORMAT_PARENT = 'name_format_parent';

    /* Learnosity configuration/credentials
     */
    public const string NAME_LEARNOSITY_VERSION = 'learnosity_version';
    public const string NAME_LEARNOSITY_CONSUMER_KEY = 'learnosity_consumer_key';
    public const string NAME_LEARNOSITY_CONSUMER_SECRET = 'learnosity_consumer_secret';

    /* OpenAI
     */
    /* @deprecated now ai feature specific \Schoolbox\Editor\OpenAI\Helper::FEATURE_* */
    public const string NAME_OPENAI_TEXT_ENDPOINT = 'openai_text_endpoint';
    /* @deprecated now ai feature specific \Schoolbox\Editor\OpenAI\Helper::FEATURE_* */
    public const string NAME_OPENAI_TEXT_KEY = 'openai_text_key';
    /* @deprecated now ai feature specific \Schoolbox\Editor\OpenAI\Helper::FEATURE_* */
    public const string NAME_OPENAI_TEXT_RESOURCE_NAME = 'openai_text_azure_resource_name';
    /* @deprecated now ai feature specific \Schoolbox\Editor\OpenAI\Helper::FEATURE_* */
    public const string NAME_OPENAI_TEXT_DEPLOYMENT_ID = 'openai_text_azure_deployment_id';
    /* @deprecated now ai feature specific \Schoolbox\Editor\OpenAI\Helper::FEATURE_* */
    public const string NAME_OPENAI_TEXT_MODEL = 'openai_text_model';
    /* @deprecated now ai feature specific \Schoolbox\Editor\OpenAI\Helper::FEATURE_* */
    public const string NAME_OPENAI_TEXT_SYSTEM_PROMPT = 'openai_text_generation_system_prompt';

    // Text Feature json config
    public const string NAME_AI_TEXT_CHAT = 'ai_text_chat';
    public const string NAME_AI_LESSONPLAN = 'ai_lessonplan';
    public const string NAME_AI_DIALOGAGENT = 'ai_dialogagent';
    public const string NAME_AI_DIFFERENTIATION = 'ai_differentiation';
    public const string NAME_AI_IMAGE_GENERATION = 'ai_image_generation';
    public const string NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION = 'ai_learning_activity_customisation';
    public const string NAME_AI_MODERATION_KEY = 'ai_moderation_key';
    public const string NAME_AI_MODERATIONAGENT = 'ai_moderationagent';

    // Presedio (anonymisation service, used by AI clients)
    public const string NAME_PRESIDIO_ANALYSIS_HOST = 'presidio_analysis_host';
    public const string NAME_PRESIDIO_ANONYMISATION_HOST = 'presidio_anonymisation_host';


    /* Differentiation profiles
     */
    public const string NAME_DIFFERENTIATION_PROFILE_ENABLED = 'differentiation_profiles_enabled';


    public const string NAME_DATASET_INSTANCE = 'dataset_instance';
    /**
     * Isams specific
     */
    public const string EXTERNAL_ISAMS_REST_API_HOST = 'external_isams_rest_api_host';
    public const string EXTERNAL_ISAMS_REST_API_CLIENT_ID = 'external_isams_rest_api_client_id';
    public const string EXTERNAL_ISAMS_REST_API_CLIENT_SECRET = 'external_isams_rest_api_client_secret';
    public const string EXTERNAL_ISAMS_STUDENT_REGISTERS = 'external_isams_student_registers';
    public const string EXTERNAL_ISAMS_FINANCIAL_REGISTERS = 'external_isams_financial_registers';


    // Notification settings
    public const string NAME_NOTIFICATION_BLACKOUT_ENABLED    = 'notification_blackout_enabled';
    public const string NAME_NOTIFICATION_BLACKOUT_START_TIME = 'notification_blackout_start_time';
    public const string NAME_NOTIFICATION_BLACKOUT_END_TIME   = 'notification_blackout_end_time';


    // Virus scanner settings
    public const string NAME_CLAMD_HOST          = 'clamd_host';
    public const string NAME_CLAMD_PORT          = 'clamd_port';
    public const string NAME_CLAMD_MAX_FILE_SIZE = 'clamd_max_file_size';


    // Storage cleanup settings
    public const string NAME_STORAGE_CLEANUP_ENABLED = 'storage_cleanup_enabled';

    // Data Retention Settings
    public const string NAME_DATA_RETENTION_EPORTFOLIO = 'dataRetention_ePortfolio';
    public const string NAME_DATA_RETENTION_LEARNINGMOMENTS = 'dataRetention_LearningMoments';
    public const string NAME_DATA_RETENTION_MYFILES = 'dataRetention_MyFiles';
    public const string NAME_DATA_RETENTION_CMS = 'dataRetention_CMS';
    public const string NAME_DATA_RETENTION_STUDENTSUBMISSIONS = 'dataRetention_StudentSubmissions';


    // Debug settings
    public const string NAME_DEBUG_MODE = 'debug_mode';

    /**
     * Config settings which may affect the behaviour of the router daemon.
     *
     * If any of these settings change: send a reload message to the router
     * daemon.
     */
    public const array SETTINGS_AFFECTING_ROUTED = [
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
    public const array SETTINGS_AFFECTING_SCHEDULED = [
        'restrict_parent_duework_access',
        'restrict_student_submissions',
        'restrict_parent_grades',
        self::NAME_MOBILE_DAEMON_ENABLED,
    ];

    /**
     * Config settings which may affect the behaviour of the mobile daemon.
     *
     * If any of these settings change: send a refresh configuration message
     * to the mobile daemon.
     */
    public const array SETTINGS_AFFECTING_MOBILED = [
        self::NAME_NOTIFICATION_BLACKOUT_ENABLED,
        self::NAME_NOTIFICATION_BLACKOUT_START_TIME,
        self::NAME_NOTIFICATION_BLACKOUT_END_TIME,
    ];

    /**
     * Config settings which affect the modules and routes available to this
     * instance.
     *
     * If any of these settings change: rebuild the route and module tables.
     */
    public const array SETTINGS_AFFECTING_MODULES = [
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
        self::NAME_PWA_ENABLED,
        self::NAME_CURRICULUM_COVERAGE_REPORT_ENABLED,
    ];

    /**
     * Config settings which affect the oAuth/OIDC provider.
     *
     * If any of these settings change: invalidate the OIDC cache.
     */
    public const array SETTINGS_AFFECTING_OAUTH = [
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
            [
                self::MODULE_SETTINGS,
                self::MODULE_COMMS,
                self::MODULE_CUSTOM,
                self::MODULE_OAUTH,
                self::MODULE_SCHOOLDATA,
                self::MODULE_LTI,
                self::MODULE_REPORT_EXPORT,
                self::MODULE_FEATURE_TOURS,
                self::MODULE_AI_SERVICES,
                self::MODULE_DIFFERENTIATION_PROFILES,
            ]
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
