<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * if staging, dev or trial, clear intellischool secret config.
 */
$app = new class extends CliTool\App {
    const STOPWORDS_TABLE_NAME = 'innodb_ft_stopword';

    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();
        $log = $this->getLogger();
        $quoter = $dbc->getQuoter();

        // Disable max_statement_time limit to prevent timeouts during creation of fulltext indexes below.
        $dbc->execute('SET SESSION max_statement_time=0');

        // Create a custom stopwords list that is the same as the default list except for the inclusion of 'and'.
        $dbc->execute(
            'CREATE TABLE IF NOT EXISTS' . $quoter->quoteIdentifier(self::STOPWORDS_TABLE_NAME) . '
            AS SELECT * FROM INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD'
        );
        $dbc->execute('INSERT INTO ' . $quoter->quoteIdentifier(self::STOPWORDS_TABLE_NAME) . " SET value = 'and'");
        $log->info("Created database table '" . self::STOPWORDS_TABLE_NAME . "'.");

        // Tell MySQL to use the table created above as the innodb_ft_stopword_table for this session only.
        // The desired effect is that MySQL uses the stopwords table when recreating the fulltext indexes below.
        // (This is set for other sessions in library/Alaress/Database/Broker/Mysql/Broker->connect().)
        $dbNameSlashTableName = SQL_db . '/' . self::STOPWORDS_TABLE_NAME;
        $dbc->prepareQuery('SET SESSION innodb_ft_user_stopword_table = ?', [$dbNameSlashTableName])->execute();
        $log->info("Set innodb_ft_user_stopword_table to '$dbNameSlashTableName'.");

        // Rebuild the search indexes.
        $dbc->execute('ALTER TABLE search_index DROP KEY IF EXISTS `search_index_fulltext_index_index`');
        $dbc->execute('ALTER TABLE search_index DROP KEY IF EXISTS `search_index_title_index`');
        $dbc->execute('CREATE FULLTEXT INDEX `search_index_fulltext_index_index` ON search_index (`fulltext_index`)');
        $dbc->execute('CREATE FULLTEXT INDEX `search_index_title_index` ON search_index (`title`)');
        $log->info('Rebuilt search indexes.');

        // The purpose of this line is explained in Search/Api/Database/Index/CommandLineTool/IndexBuilder->run().
        $dbc->execute('OPTIMIZE TABLE search_index');
        $log->info('Optimized search_index table.');
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO], true))->runApp();
