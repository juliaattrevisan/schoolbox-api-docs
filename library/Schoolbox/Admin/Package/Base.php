<?php

namespace Schoolbox\Admin\Package;

use Alaress\Csv\Reader as CsvReader;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\User;

class Base
{
    /**
     * list of packages this depends on existing in the system
     */
    public const REQUIRED_PACKAGES = [];

    private const SOURCE_FILE = 'curriculum.csv';

    /**
     * Constructor.
     *
     * @param string      $path // Path to the installer file and csv
     * @param Mapper|null $curriculumMapper // Spawn this class with a mapper, or it will do it for you... either way one is needed
     */
    public function __construct(private string $path, private ?Node\Mapper $curriculumMapper = null)
    {
        if (null === $this->curriculumMapper) {
            $this->curriculumMapper = Node\Mapper::getInstance();
        }
    }

    public function install(User $user): void
    {
        if (!(is_file($this->path  . '/' . self::SOURCE_FILE) && is_readable($this->path  . '/' . self::SOURCE_FILE))) {
            throw new \RuntimeException('Package source file not found: ' . $this->path  . '/' . self::SOURCE_FILE);
        }

        $csv = new CsvReader($this->path  . '/' . self::SOURCE_FILE);
        $csv->setLoadFromFirst(true);
        $parentLookup     = [];

        foreach ($csv as $row) {
            $node = $this->curriculumMapper->createModel();
            $node->setParent($row['curriculum_node_parent_id'] ? $parentLookup[$row['curriculum_node_parent_id']] : null);
            $node->setName($row['name']);
            $node->setDescription($row['description']);
            $node->setCode(trim($row['code']));
            $node->setOrder($row['order']);

            $this->curriculumMapper->save($node);

            //Store the inserted ID so we can link children to it
            $parentLookup[$row['id']] = $node;
        }
    }
}
