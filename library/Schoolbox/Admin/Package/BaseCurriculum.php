<?php

namespace Schoolbox\Admin\Package;

use Alaress\Csv\Reader as CsvReader;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\User;

abstract class BaseCurriculum implements IPackageInstall
{
    /**
     * list of packages this depends on existing in the system
     * @var array
     */
    protected array $requiredPackages = [];

    /**
     * the path for the curriculum csv file
     *
     * requires to be overloaded
     * will require the __DIR__ in the package directory, or the absolute path
     * __DIR__ is the directory of the file who contains __DIR__
     * .ie this BaseCurriculum file __DIR__ is `/usr/share/schoolbox/library/Schoolbox/Admin/Package/`
     *
     * @var string
     */
    protected string $sourceFile = __DIR__ . '/exampleFile.csv';

    /**
     * Constructor.
     *
     * @param Mapper|null $curriculumMapper // Spawn this class with a mapper, or it will do it for you... either way one is needed
     */
    public function __construct(private ?Node\Mapper $curriculumMapper = null)
    {
        if (null === $this->curriculumMapper) {
            $this->curriculumMapper = Node\Mapper::getInstance();
        }
    }

    /**
     * Return the list of the packages this package depends on
     * @return array
     */
    public function getRequiredPackages(): array
    {
        return $this->requiredPackages;
    }

    /**
     * Generic function to install the package
     *
     * @param User $user
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Resource\Exception
     */
    public function install(User $user): void
    {
        if (!(is_file($this->sourceFile) && is_readable($this->sourceFile))) {
            throw new \RuntimeException('Package source file not found: ' . $this->sourceFile);
        }

        $csv = new CsvReader($this->sourceFile);
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

    /**
     * @return Mapper
     */
    public function getCurriculumMapper(): Mapper
    {
        return $this->curriculumMapper;
    }
}
