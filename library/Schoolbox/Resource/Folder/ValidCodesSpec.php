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
use function array_merge;
use function array_unique;
use function count;
use function strpos;
use function substr;
use Schoolbox\Mapper;
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

    /** @var Mapper */
    private $folderMapper;

    /** @var Code\Mapper */
    private $folderCodeMapper;


    /**
     * @param Mapper      $folderMapper
     * @param Code\Mapper $folderCodeMapper
     */
    public function __construct(Mapper $folderMapper, Code\Mapper $folderCodeMapper)
    {
        $this->folderMapper     = $folderMapper;
        $this->folderCodeMapper = $folderCodeMapper;
    }


    /**
     * Returns whether the set of proposed folder codes are valid
     *
     * @param Model $candidate
     * @return bool
     * @throws Mapper\DefinitionException
     * @throws Database\Predicate\InvalidColumnException
     */
    public function isSatisfiedBy($candidate): bool
    {
        // 1. Check candidate's codes are unique
        $codes = $candidate->getCodes()->getPropertyValues('code');
        if (count($codes) !== count(array_unique($codes))) {
            return false;
        }

        // Generate a indexed map for O(1) lookup
        $indexedCodes = array_combine($codes, array_fill(0, count($codes), null));

        // 2. Check candidate's prefixed and raw codes don't conflict
        [$rawCodes, $prefixedCodes] = $this->filterRawAndPrefixedCodes($indexedCodes);

        // Will include raw codes, and campus-prefixed codes with the campus prefix removed.
        $strippedCodes = [];
        foreach (array_keys($prefixedCodes) as $prefixedCode) {
            $stripped = $this->unPrefixCode($prefixedCode);
            if (array_key_exists($stripped, $rawCodes)) {
                return false;
            }
            $strippedCodes[$stripped] = null;
        }

        $otherFoldersIndexedCodes = $this->getOtherFoldersCodes($candidate->getId(), $rawCodes, $strippedCodes);
        [$otherRawCodes] = $this->filterRawAndPrefixedCodes($otherFoldersIndexedCodes);


        // 3. Check candidate's codes don't have an exact match with other folders' codes
        foreach (array_keys($indexedCodes) as $code) {
            if (array_key_exists($code, $otherFoldersIndexedCodes)) {
                return false;
            }
        }

        // 4. Check candidate's prefixed codes don't conflict with other folders' raw codes
        foreach (array_keys($strippedCodes) as $stripped) {
            if (array_key_exists($stripped, $otherRawCodes)) {
                return false;
            }
        }

        return true;
    }


    /**
     * Returns codes from other folders that are EQUAL or LIKE the specified stripped codes
     *
     * @param int|null $folderId
     * @param null[]   $rawCodes
     * @param null[]   $strippedCodes
     * @return null[]
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\DefinitionException
     */
    private function getOtherFoldersCodes(?int $folderId, array $rawCodes, array $strippedCodes): array
    {
        $pb = $this->folderMapper->getJoinMapper('codes')->getPredicateBuilder();
        if ($folderId) {
            $pb->notEquals('folder_id', $folderId);
        } else {
            $pb->notNull('folder_id');
        }
        $innerPb = $this->folderMapper->getJoinMapper('codes')->getPredicateBuilder();

        $rawAndStripped = array_merge($rawCodes, $strippedCodes);

        foreach (array_keys($rawAndStripped) as $code) {
            $innerPb->equals('code', $code);
            $innerPb->like('code', "%/" . $this->folderMapper->getDatabase()->escapeForLike($code));
        }
        $pb->any($innerPb);

        $otherFolderCodes = array_unique($this->folderCodeMapper->findScalarByPredicates('code', $pb));
        return array_combine($otherFolderCodes, array_fill(0, count($otherFolderCodes), null));
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
