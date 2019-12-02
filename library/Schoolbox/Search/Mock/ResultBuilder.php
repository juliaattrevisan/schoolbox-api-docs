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
//        self::RESULT_TYPE_EVENT,
        self::RESULT_TYPE_FOLDER,
//        self::RESULT_TYPE_COMMS,
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
            'id'   => $this->faker->numberBetween(1, 999999),
            'type' => $resultType,
        ];
        switch ($resultType) {
            case self::RESULT_TYPE_FOLDER:
                return $baseResult + [
                    'content' => $this->generateFolder($baseResult['id']),
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
     * Generate a folder.
     *
     * @param int $id
     * @return Folder
     */
    private function generateFolder(int $id): Folder
    {
        return (new Folder())
            ->setId($id)
            ->setName($this->faker->bs);
    }
}
