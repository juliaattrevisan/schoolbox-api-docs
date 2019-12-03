<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Mock;

use Faker\Generator;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Search mock result builder.
 */
class ResultBuilder
{
    private const RESULT_TYPE_USER       = 'user';
    private const RESULT_TYPE_EVENT      = 'event';
    private const RESULT_TYPE_FOLDER     = 'folder';
    private const RESULT_TYPE_COMMS      = 'comms';
    private const RESULT_TYPE_ASSESSMENT = 'assessment';
    private const RESULT_TYPE_FILE       = 'file';
    private const RESULT_TYPE_TEXTBOX    = 'textbox';
    private const RESULT_TYPE_LINK       = 'link';
    private const RESULT_TYPE_TILE       = 'tile';
    private const RESULT_TYPE_FORUM      = 'forum';


    private const RESULT_TYPES_BASE = [
        self::RESULT_TYPE_USER,
        self::RESULT_TYPE_EVENT,
        self::RESULT_TYPE_FOLDER,
        self::RESULT_TYPE_COMMS,
    ];


    private const RESULT_TYPES_IN_FOLDER = [
        self::RESULT_TYPE_EVENT,
        self::RESULT_TYPE_COMMS,
        self::RESULT_TYPE_ASSESSMENT,
        self::RESULT_TYPE_FILE,
        self::RESULT_TYPE_TEXTBOX,
        self::RESULT_TYPE_LINK,
        self::RESULT_TYPE_TILE,
        self::RESULT_TYPE_FORUM,
    ];


    /**
     * @var Generator|null
     */
    private $faker;


    /**
     * Constructor
     *
     * @param Generator|null $faker
     */
    public function __construct(?Generator $faker)
    {
        $this->faker = $faker;
    }


    /**
     * Generates a mock search result list.
     *
     * @param int $count How many items in the search result?
     *
     * @return array
     */
    public function generateResultList(int $count): array
    {
        // Options:
        // errorChance?
        // searchTerm
        // date from/to
        // creator
        // cursor
        // allowed types
        // type count?

        $resultList = [];
        if ($count > 0) {
            foreach (range(1, $count) as $i) {
                $resultList[] = $this->generateResult();
            }
        }
        return [
            'data'     => $resultList,
            'metadata' => [
                // There could maybe be more results?
                'count'  => $count,
                'cursor' => [
                    // Ignore cursors for now?
                    'current' => 'abc',
                    'next'    => 'def',
                ],
            ],
        ];
    }


    /**
     * Create a result.
     *
     * @return array
     */
    private function generateResult(): array
    {
        $resultType = $this->faker->randomElement(self::RESULT_TYPES_BASE);
        $baseResult = [
            'id'      => $this->faker->numberBetween(1, 999999),
            'type'    => $resultType,
            'summary' => $this->faker->text,
        ];
        switch ($resultType) {
            case self::RESULT_TYPE_FOLDER:
                return $baseResult + [
                    'content' => $this->generateFolder($baseResult['id']),
                ];

            case self::RESULT_TYPE_COMMS:
                return $baseResult + [
                    'content' => $this->generateComms($baseResult['id']),
                ];

            case self::RESULT_TYPE_EVENT:
                return $baseResult + [
                    'content' => $this->generateEvent($baseResult['id']),
                ];

            case self::RESULT_TYPE_USER:
            default:
                return $baseResult + [
                    'content' => $this->generateUser($baseResult['id']),
                ];
        }
    }


    /**
     * Generate a user.
     *
     * @param int $id
     * @return User
     */
    private function generateUser(int $id): User
    {
        $gender = $this->faker->randomElement(['male', 'female']);
        return (new User())
            ->setId($id)
            ->setExternalId($this->faker->word)
            ->setTitle($this->faker->title($gender))
            ->setFirstname($this->faker->firstName($gender))
            ->setLastname($this->faker->lastName);
    }


    /**
     * Generate a folder result.
     *
     * @param int $id
     * @return array
     */
    private function generateFolder(int $id): array
    {
        $subResultCount = $this->faker->numberBetween(0, 5);
        $subResultList  = [];
        if ($subResultCount > 0) {
            foreach (range(1, $subResultCount) as $i) {
                $subResultList[] = $this->generateFolderSubResult();
            }
        }
        return [
            'id'         => $id,
            'title'      => $this->faker->bs,
            'subResults' => $subResultList,
        ];
    }


    /**
     * Generate a fake comms article result.
     *
     * @param int $id
     * @return array
     */
    private function generateComms(int $id): array
    {
        return [
            'id'      => $id,
            'title'   => $this->faker->sentence(5),
            'publish' => $this->faker->dateTimeBetween('-1 year', '-1 day')->format(\DateTime::RFC3339),
            'archive' => $this->faker->dateTimeBetween('+1 day', '+1 year')->format(\DateTime::RFC3339),
        ];
    }


    /**
     * Generate a fake event result.
     *
     * @param int $id
     * @return array
     */
    private function generateEvent(int $id): array
    {
        $allDay     = $this->faker->boolean(25);
        $dateFormat = $allDay ? 'Y-m-d' : \DateTime::RFC3339;
        return [
            'id'     => $id,
            'title'  => $this->faker->sentence(5),
            'allDay' => $allDay,
            'start'  => $this->faker->dateTimeBetween('-1 week', '-1 hour')->format($dateFormat),
            'end'    => $this->faker->dateTimeBetween('+1 hour', '+1 week')->format($dateFormat),
        ];
    }


    /**
     * Generate a sub-result
     *
     * @return array
     */
    private function generateFolderSubResult(): array
    {
        $resultType = $this->faker->randomElement(self::RESULT_TYPES_IN_FOLDER);
        $baseResult = [
            'id'      => $this->faker->numberBetween(1, 999999),
            'type'    => $resultType,
            'summary' => $this->faker->text,
        ];
        switch ($resultType) {
            case self::RESULT_TYPE_COMMS:
                return $baseResult + [
                    'content' => $this->generateComms($baseResult['id']),
                ];

            case self::RESULT_TYPE_EVENT:
            default:
                return $baseResult + [
                    'content' => $this->generateEvent($baseResult['id']),
                ];
        }
    }
}
