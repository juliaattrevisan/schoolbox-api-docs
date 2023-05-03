<?php

namespace Schoolbox\Admin\Package;

use Alaress\Csv\Reader as CsvReader;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Learning\Badge;
use Schoolbox\Learning\Badge\Category\Mapper as BadgeCategoryMapper;
use Schoolbox\User;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

abstract class BaseBadges
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
     * Either inject these mappers, or I will spawn them for you... either way they are needed
     *
     * @param CurriculumMapper|null     $curriculumMapper
     * @param BadgeMapper|null          $badgeMapper
     * @param YearLevelMapper|null      $yearLevelMapper
     * @param BadgeCategoryLocator|null $badgeCategoryLocator
     */
    public function __construct(
        private ?CurriculumMapper $curriculumMapper = null,
        private ?BadgeMapper $badgeMapper = null,
        private ?YearLevelMapper $yearLevelMapper = null,
        private ?BadgeCategoryLocator $badgeCategoryLocator = null
    ) {
        if (null === $this->curriculumMapper) {
            $this->curriculumMapper = CurriculumMapper::getInstance();
        }
        if (null === $this->badgeMapper) {
            $this->badgeMapper = BadgeMapper::getInstance();
        }
        if (null === $this->yearLevelMapper) {
            $this->yearLevelMapper = YearLevelMapper::getInstance();
        }
        if (null === $this->badgeCategoryLocator) {
            $this->badgeCategoryLocator = new BadgeCategoryLocator(BadgeCategoryMapper::getInstance());
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
     * Install rows from badge.csv under the presumption that an instance's Year Levels
     * are configured in a specific way as described by the csv's Year column.
     *
     * It must match exactly as it is or exactly when the "Year " prefix is added
     * to the column value.
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \PDOException
     */
    public function install(User $user): void
    {
        if (!(is_file($this->sourceFile) && is_readable($this->sourceFile))) {
            throw new \RuntimeException('Package source file not found: ' . $this->sourceFile);
        }

        $csv = (new CsvReader($this->sourceFile))->setLoadFromFirst(true);

        foreach ($csv as $row) {
            /* Decide which year levels to associate with this badge
             */
            $yearList = $this->yearLevelMapper->createCollection();
            if (isset($row['Year'])) {
                foreach (explode(',', $row['Year']) as $year) {
                    try {
                        $yearObject = $this->yearLevelMapper->findOneByColumn(YearLevelMapper::COL_NAME, $year);
                        $yearList->add($yearObject);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        //retry but this time with the word 'Year ' in front.
                        try {
                            $yearObject = $this->yearLevelMapper->findOneByColumn(YearLevelMapper::COL_NAME, 'Year ' . $year);
                            $yearList->add($yearObject);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            //noop
                        }
                    }
                }
            }

            /* Decide which curriculum codes to associate with this badge
             */
            $curriculumCodes = explode(',', $row['Curriculum']);
            $nodeCollection  = $this->curriculumMapper->createCollection();

            foreach ($curriculumCodes as $code) {
                if ($code === '') {
                    continue;
                }

                $nodeCollection->add($this->curriculumMapper->findOneByColumn('code', $code));
            }

            /* Assign values to the new badge instance
             */
            $badge = $this->badgeMapper->createModel()
                                 ->setName($row['Title'])
                                 ->setBody('<p>' . $row['Description'] . '</p>')
                                 ->setIcon('/images/badges' . $row['Image'])
                                 ->setCategory($this->badgeCategoryLocator->locate($row['Category'], $user))
                                 ->setAchievementExpiry(Badge\Model::EXPIRY_NEVER)
                                 ->setGoalExpiry(Badge\Model::EXPIRY_NEVER)
                                 ->setAllowAsGoal((bool)$row['Goal'])
                                 ->setCurriculumNodes($nodeCollection)
                                 ->setCreatedBy($user)
                                 ->setUpdatedBy($user)
                                 ->setAnimate(true);

            // If there are year levels, then add them
            if ($yearList->count()) {
                $badge->setYearLevels($yearList);
            }

            $this->badgeMapper->save($badge);
        }
    }
}
