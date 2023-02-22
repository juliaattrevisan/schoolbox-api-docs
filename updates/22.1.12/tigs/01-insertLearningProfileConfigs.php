#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\WorkType\Mapper;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    /**
     * New configurations for learning profiles.
     */
    private const NEW_CONFIGS = [
        [
            'name'        => 'tigs_crp_work_type_year_7_10_term_%d_learning_profile',
            'title'       => 'Work Type: Learning Profile Year 7-10, Term %d',
            'description' => 'Work type to use for the Learning Profile for years 7-10, term %d',
            'terms'       => [1, 2, 3, 4],
        ],
        [
            'name'        => 'tigs_crp_work_type_year_11_term_%d_learning_profile',
            'title'       => 'Work Type: Learning Profile Year 11, Term %d',
            'description' => 'Work type to use for the Learning Profile for year 11, term %d',
            'terms'       => [1, 2, 3],
        ],
        [
            'name'        => 'tigs_crp_work_type_year_12_term_%d_learning_profile',
            'title'       => 'Work Type: Learning Profile Year 12, Term %d (previous year)',
            'description' => 'Work type to use for the Learning Profile for year 12, term %d (previous year)',
            'terms'       => [4],
        ],
        [
            'name'        => 'tigs_crp_work_type_year_12_term_%d_learning_profile',
            'title'       => 'Work Type: Learning Profile Year 12, Term %d',
            'description' => 'Work type to use for the Learning Profile for year 12, term %d',
            'terms'       => [1, 2, 3],
        ],
    ];

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        $insertConfigQuery = $this->getDatabaseInternal()->prepareQuery("
            INSERT INTO config (
                name,
                value,
                title,
                description,
                field_id,
                options,
                type_id,
                editable,
                hidden,
                required,
                module,
                sequence
            ) VALUES (
                :name,
                '',
                :title,
                :description,
                (SELECT id FROM lu_config_field WHERE field='selectLookup'),
                :options,
                null,
                'true',
                'false',
                'true',
                'custom',
                null
            )
        ");
        foreach (self::NEW_CONFIGS as $newConfig) {
            foreach ($newConfig['terms'] as $term) {
                $params = [
                    'options'     => serialize(['mapper' => Mapper::class]),
                    'name'        => sprintf($newConfig['name'], $term),
                    'title'       => sprintf($newConfig['title'], $term),
                    'description' => sprintf($newConfig['description'], $term),
                ];
                $insertConfigQuery->execute($params);
            }
        }
    }

    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::DEBUG, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, $options))->runApp();
