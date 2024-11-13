<?php

namespace Schoolbox\Admin\Package;

use Alaress\Csv\Reader as CsvReader;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\User;

abstract class BaseCurriculum implements IPackageInstall
{
    /**
     * Safety mechanism to avoid infinite loops
     */
    private const MAX_CALLS = 100;


    /**
     * Some kind of export includes this word for a column with
     * the datatype null, instead of leaving it empty
     */
    private const NULL_STRING = 'NULL';

    /**
     * A list of (csv row id, model id) tuples
     *
     * @var array
     */
    private array $parentLookup = [];

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
        /* Note that csvs can contain html, breaking the column enclosures,
          * causing random exceptions and failures. You can solve it with this
          * cheap test.php
          *
          * new \Alaress_Probe(strip_tags(file_get_contents($this->$this->sourceFile)));
          *
          * Alternatively we can cleanse it, then write to disk for CsvReader to
          * load from the specified $this->$this->sourceFile...
          *
          * ...but it'd be better to feed it functional data instead, from the outset
          */
        $csv = (new CsvReader($this->sourceFile))->setLoadFromFirst(true);

        /* You can no longer presume that the csv nodes are in hierarchical order
         *
         * Some rows reference parent rows that have yet to exist, so you need to add them in place;
         * but to do that, you need to know what those parent nodes should be, which means you
         * need to have read ahead
         */
        $graph = [];

        foreach ($csv as $row) {
            $graph[$row['id']] = $row;
        }

        /* A pretty-bad usecase is when:
         * csv row 1 is the root node
         * csv row 2 is some distant grandchild node (call it "Alice")
         * csv row 3000 is the actual parent node for csv row 2 (call it "Frank")
         *
         * Importing the Alice node will cause importAncestors() to build the entire
         * ancestry, up to and including Frank
         *
         * When Frank is finally encountered, it'll create a new $node unless
         * you skip it by checking the $parentLookup hash
         */
        foreach ($graph as $row) {
            /* Import $row's parent nodes, all the way back to root
             */
            $this->importAncestors($graph, $row);

            /* Import $row itself
            */
            $this->importNode($row);
        }
    }

    /**
     * For the given $row, import its parent node and all the ancestral nodes,
     * right back until the root node is encountered - this is defined by having
     * a null curriculum_node_parent_id
     *
     * @param array $graph
     * @param array $row
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importAncestors(array $graph, array $row): void
    {
        $next = $row['curriculum_node_parent_id'];

        /* Treat this special word with special care
         */
        if (self::NULL_STRING === $next) {
            $next = null;
        }

        /* Have a max of MAX_CALL ancestors
         * I guess 100 might be enough, but reconfigure it as you need
         */
        for ($i = 0; $next && $i < self::MAX_CALLS; $i++) {
            if (!isset($this->parentLookup[$next])) {
                $uuid = $parent['uuid'] ?? null;
                /* check if uuid is empty string, if set it to null
                 */
                if ($uuid === '') {
                    $uuid = null;
                }
                $parent = $graph[$row['curriculum_node_parent_id']];

                $node = $this->getCurriculumMapper()->createModel()
                             ->setParent($parent['curriculum_node_parent_id'] ? $this->parentLookup[$parent['curriculum_node_parent_id']] : null)
                             ->setName($parent['name'])
                             ->setDescription($parent['description'])
                             ->setCode(trim($parent['code']))
                             ->setOrder($parent['order'])
                             ->setUuid($uuid);

                $this->getCurriculumMapper()->save($node);

                // Store the inserted ID so we can link children to it
                $this->parentLookup[$parent['id']] = $node;

                /* Because NULL string is not the same as a NULL data type
                 * and people are going to forget to clear this in the csv
                 */
                if (self::NULL_STRING === $parent['curriculum_node_parent_id']) {
                    $next = null;
                } else {
                    $next = (int)$parent['curriculum_node_parent_id'];
                }
            }
        }
    }


    /**
     * Import the $row node
     *
     * @param array $row
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importNode(array $row): void
    {
        /* $row might have been imported by importAncestors()
         *
         * This predominantly affects immediate descendents of root node
         * if they appear in the csv at the very end, but the first few
         * lines of csv use those as parents
         */
        if (isset($this->parentLookup[$row['id']])) {
            return;
        }

        $parentId = $row['curriculum_node_parent_id'];

        /* Treat this special word with special care
         */
        if (self::NULL_STRING === $parentId) {
            $parentId = null;
        }

        $uuid = $row['uuid'] ?? null;
        /* check if uuid is empty string, if set it to null
         */
        if ($uuid === '') {
            $uuid = null;
        }

        $node = $this->getCurriculumMapper()->createModel()
                     ->setParent($parentId ? $this->parentLookup[$row['curriculum_node_parent_id']] : null)
                     ->setName($row['name'])
                     ->setDescription($row['description'])
                     ->setCode(trim($row['code']))
                     ->setOrder($row['order'])
                     ->setUuid($uuid);

        $this->getCurriculumMapper()->save($node);

        // Store the inserted ID so we can link children to it
        $this->parentLookup[$row['id']] = $node;
    }

    /**
     * @return Mapper
     */
    public function getCurriculumMapper(): Mapper
    {
        return $this->curriculumMapper;
    }
}
