<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use Alaress\Specification\Leaf;
use function array_combine;
use function array_fill;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function count;
use function strpos;
use function substr;
use Alaress\Database;

/**
 * Efficiently validates that a folder's codes are valid compared to:
 * * other codes on the folder, and
 * * codes on other folders.
 *
 * Rules:
 * * Non-prefixed and Campus-prefixed collisions are invalid, eg ['ABC', 'S/ABC'] is invalid
 * * Campus-prefixed are permitted if campus prefix is unique, eg ['J/ABC', 'S/ABC'] is valid
 */
class ValidCodesSpec extends Leaf
{

    /** @var Code\Mapper */
    private $folderCodeMapper;


    /**
     * @param Code\Mapper $folderCodeMapper
     */
    public function __construct(Code\Mapper $folderCodeMapper)
    {
        $this->folderCodeMapper = $folderCodeMapper;
    }


    /**
     * Returns whether the set of proposed folder codes are valid
     *
     * @param Model $candidate
     * @return bool
     * @throws Database\Predicate\InvalidColumnException
     */
    public function isSatisfiedBy($candidate): bool
    {
        // If new folder has no codes, it can never collide.
        $codes = $candidate->getCodes()->getPropertyValues('code');
        if (!count($codes)) {
            return true;
        }

        // MySQL is case-insensitive by default, so we will need to mimic that here
        $codes = array_map('strtolower', $codes);

        // 1. Check candidate's codes are unique
        if (count($codes) !== count(array_unique($codes))) {
            return false;
        }

        // Generate a indexed map for O(1) lookup
        $indexedCodes = array_combine($codes, array_fill(0, count($codes), null));

        // 2. Check candidate's prefixed and raw codes don't conflict
        [$rawCodes, $prefixedCodes] = $this->filterRawAndPrefixedCodes($indexedCodes);

        // Will include raw codes, and campus-prefixed codes with the campus prefix removed.
        foreach (array_keys($prefixedCodes) as $prefixedCode) {
            $stripped = $this->unPrefixCode($prefixedCode);
            if (array_key_exists($stripped, $rawCodes)) {
                return false;
            }
        }

        // 3. Check candidate's codes against codes already existing on other folders
        return 0 === $this->getCountOtherFoldersCodes($candidate->getId(), $rawCodes, $prefixedCodes);
    }


    /**
     * Returns the count of other codes that are would collide with the specified codes
     *
     * @param int|null $folderId
     * @param null[]   $rawCodes
     * @param null[]   $prefixedCodes
     * @return int
     * @throws Database\Predicate\InvalidColumnException
     */
    private function getCountOtherFoldersCodes(?int $folderId, array $rawCodes, array $prefixedCodes): int
    {
        $pb = $this->folderCodeMapper->getPredicateBuilder();
        if ($folderId) {
            $pb->notEquals('folder_id', $folderId);
        } else {
            $pb->notNull('folder_id');
        }
        $innerPb = $this->folderCodeMapper->getPredicateBuilder();

        foreach (array_keys($rawCodes) as $code) {
            $innerPb->equals('code', $code);
            $innerPb->like('code', "%/" . $this->folderCodeMapper->getDatabase()->escapeForLike($code));
        }
        foreach (array_keys($prefixedCodes) as $prefixedCode) {
            $innerPb->equals('code', $prefixedCode);
            $innerPb->equals('code', $this->unPrefixCode($prefixedCode));
        }
        $pb->any($innerPb);

        return $this->folderCodeMapper->findByPredicates($pb)->count();
    }


    /**
     * Filters the specified codes into non-prefixed and prefixed sets
     *
     * @param null[] $codes
     * @return null[][]
     */
    private function filterRawAndPrefixedCodes(array $codes): array
    {
        $rawCodes = $prefixedCodes = [];
        foreach (array_keys($codes) as $code) {
            $idx = strpos($code, '/');
            if ($idx === false) {
                $rawCodes[$code] = null;
            } else {
                $prefixedCodes[$code] = null;
            }
        }
        return [$rawCodes, $prefixedCodes];
    }


    /**
     * Strips the prefix from a campus-prefixed code.
     *
     * Should only be called with codes that are known to be campus-prefixed
     *
     * @param string $prefixedCode
     * @return string
     */
    private function unPrefixCode(string $prefixedCode): string
    {
        return substr($prefixedCode, strpos($prefixedCode, '/') + 1);
    }
}
