<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\Base;
use Alaress\Database\Broker\Mysql\Broker;
use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy;
use PDO;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Class CanvasDb
 *
 * Establish a db connection to the local canvas db cache
 * We suck down canvas data into this db, and then later,
 * we read from it to put it into the schoolbox db itself
 */
class CanvasDb
{
    /**
     * @var Base|null
     */
    private ?Base $connector = null;

    private ?string $databaseName = null;

    /**
     * @deprecated
     * TODO: OLD CANVAS DATA - REMOVE THIS IN V24
     */
    public bool $isCanvasData2 = false;

    // Product-ise/productize configs
    public readonly bool $import_quiz;
    public readonly ?int $quiz_worktype;
    public readonly bool $import_grades;
    public readonly ?int $grade_groups;
    public readonly string $sissourceid_template;
    public readonly bool $sync_news;
    public readonly ?int $news_topic;
    public readonly ?int $news_author;
    public readonly bool $redirect_assessments;
    public readonly bool $redirect_homepages;
    public readonly ?string $code_redirect_homepages;
    // options
    // course code:subject code:subject code from section
    public const REDIRECT_COURSE_CODE = 'course_code';
    public const REDIRECT_SUBJECT_CODE = 'subject_code';
    public const REDIRECT_SUBJECT_CODE_SECTION = 'subject_code_section';

    /**
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->isCanvasData2 = $config->get(ConfigMapper::_CANVAS_DATA2_SECRET)
            && $config->get(ConfigMapper::_CANVAS_DATA2_CLIENT_ID);

        $this->import_quiz = (bool)$config->get(ConfigMapper::NAME_CANVAS_IMPORT_QUIZ);
        $this->quiz_worktype = (int)$config->get(ConfigMapper::NAME_CANVAS_QUIZ_WORKTYPE);
        $this->import_grades = (bool)$config->get(ConfigMapper::NAME_CANVAS_IMPORT_GRADES);
        $this->grade_groups = (int)$config->get(ConfigMapper::NAME_CANVAS_GRADE_GROUPS);
        $this->sissourceid_template = $config->get(ConfigMapper::NAME_CANVAS_SISSOURCEID_TEMPLATE);
        $this->sync_news = (bool)$config->get(ConfigMapper::NAME_CANVAS_SYNC_NEWS);
        $this->news_topic = (int)$config->get(ConfigMapper::NAME_CANVAS_NEWS_TOPIC);
        $this->news_author = (int)$config->get(ConfigMapper::NAME_CANVAS_NEWS_AUTHOR);
        $this->redirect_assessments = (bool)$config->get(ConfigMapper::NAME_CANVAS_REDIRECT_ASSESSMENTS);
        $this->redirect_homepages = (bool)$config->get(ConfigMapper::NAME_CANVAS_REDIRECT_HOMEPAGES);
        $this->code_redirect_homepages = $config->get(ConfigMapper::NAME_CANVAS_CODE_REDIRECT_HOMEPAGES);
    }

    /**
     * The local canvas database is a concatenated instance identifier
     * after a hardcoded prefix; it's a whole separate db than the usual
     * schoolbox one
     *
     * @return string
     */
    public function getCanvasDbName(): string
    {
        if (!$this->databaseName) {
            /**
             * @deprecated
             * TODO: OLD CANVAS DATA - REMOVE THIS IN V24
             */
            $this->databaseName = $this->isCanvasData2 ? 'canvas_data2_' . INSTANCE_ID : 'canvas_data_' . INSTANCE_ID;
        }
        return $this->databaseName;
    }

    /**
     * Returns a connection to the Canvas staging database
     *
     * @return IConnector
     * @throws Database\Exception
     */
    public function getConnector(): IConnector
    {
        /* Only spawn this once
         */
        if (null === $this->connector) {
            $connector = new Base(new Broker(), new Transactor(new Strategy\Nested()), new Database\Quoter\Mysql\Quoter());

            $connectionOptions = [
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4; SET MAX_STATEMENT_TIME=0",
                PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                PDO::MYSQL_ATTR_LOCAL_INFILE_DIRECTORY => true,
            ];

            $connector->connect(
                SQL_host,
                $this->getCanvasDbName(),
                SQL_user,
                SQL_passwd,
                $connectionOptions
            );

            $this->connector = $connector;
        }

        return $this->connector;
    }
}
