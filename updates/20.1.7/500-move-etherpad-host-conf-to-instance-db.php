#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Copies ETHERPAD_HOST config from file to instance database
 */
$app = new class extends CliTool\App {

    public function run(): void
    {
        if (defined('ETHERPAD_HOST')) {
            $etherPadHost = ETHERPAD_HOST;
        } else {
            $this->getLogger()->warning("ETHERPAD_HOST is not defined, falling back to 'http://localhost:9001'");
            $etherPadHost = 'http://localhost:9001';
        }

        $sql = "
            INSERT INTO config (
                name,
                value,
                title,
                description,
                field_id,
                type_id,
                editable,
                hidden,
                required,
                module,
                sequence
            ) VALUES (
                :name,
                :value,
                :title,
                :description,
                (SELECT id FROM lu_config_field WHERE field = 'input'),
                (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
                :editable,
                :hidden,
                :required,
                :module,
                (SELECT * FROM (SELECT MAX(sequence) + 1 FROM config WHERE type_id = (SELECT id FROM lu_config_type WHERE type = '3rd Party Services')) t1)
            )";
        $this->getDatabaseInternal()->executeQuery(
            $sql,
            [
                'name'        => ConfigMapper::NAME_ETHERPAD_HOST,
                'value'       => $etherPadHost,
                'title'       => "Etherpad host",
                'description' => "Etherpad url including scheme and port",
                'editable'    => 'false',
                'hidden'      => 'true',
                'required'    => 'true',
                'module'      => ConfigMapper::MODULE_SETTINGS,
            ]
        );
        $this->getLogger()->notice(sprintf("Inserted new config 'Etherpad host' with value '%s'", $etherPadHost));
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
