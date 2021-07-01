<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\staloy;

use function array_keys;
use function count;
use function max;
use function preg_match;
use function preg_replace;

/**
 * Responsible for the logical link between a Canvas CourseSection.SisSourceId and a Schoolbox Folder.FolderCode
 *
 * This encapsulates the link between a logical 'Class' in both systems.
 *
 * The StAlloy scheme appears to be course_section.sis_source_id = CONCAT($year, 'C', $subjectCode, 'NOT')
 *
 * Note that we need to build a tree that contains the 'Calendar Year' folder ancestor of a class, because we need
 * both the class's folderCode, and ancestral year, to match against the SIS Source Id.
 *
 * @todo, snap the scheme into a stateless Bridge\CourseSection class, and the execution and logging into a utility class
 */
class CourseSection extends \External\Canvas\Sync\Bridge\CourseSection
{

    /**
     * @inheritDoc
     */
    public function syncCanvasMarks(): bool
    {
        return false;
    }

    /**
     * Optionally remaps some folder codes to account for T3 and T4 rollover.
     *
     * Returns any re-mappings that were applied
     *
     * We need to be able to map classes between Canvas:Schoolbox:Synergetic during the rollover period.
     * STALOY has two roll ever events:
     * 1. At the end of T3.
     *    Yr 11 students begin Yr12 classes.
     *    In Synergetic, these have FileYear=2019 and classCode~=11Bio1
     *    In Schoolbox, all Yr 11 classes are rolled over
     *    In Schoolbox, early-start class folders are created below the next calendar year, eg 2020 > 11 > 11Bio1
     * 2. At the end of T4
     *    Yr 11 folder (if it exists in SB) is renamed to '12'
     *    All folder codes of early start classes are incremented, eg 'S/11Bio1' => 'S/12Bio1'
     *    All non-early start class folders are created, eg 2020 > 11 > 11Bio1
     *
     * We need to be able to:
     * 1. Map the early start classes to a Canvas courses after the T3 event
     * 2. Continue to map classes after the T4 event
     *
     * To do so, we need to sometimes increment the year level in the folder code on the fly.
     * We will do so if the data indicates we are between the T3 and T4 events.
     * @return string[][]
     */
    public function reMapFolderCodesForRollOver(): array
    {
        // Examine latest data to see if we need to tweak the map indexes
        $reMappedFolderCodes = [];
        $years               = array_keys($this->yearAndFolderCodeToFolderMap);
        if (count($years)) {
            $latestYear = max($years);
            $this->log->notice("Examining $latestYear folder codes to determine if we are in T3-T4 period");
            $latestFolderCodes = $this->yearAndFolderCodeToFolderMap[$latestYear];
            $folderCodes       = array_keys($latestFolderCodes);

            $countYear11 = 0;
            foreach ($folderCodes as $folderCode) {
                if (preg_match("/\D?11\D+/", $folderCode, $matches)) {
                    $countYear11++;
                }
            }
            $countFolderCodes = count($folderCodes);
            $this->log->info("Count $latestYear folder codes: $countFolderCodes");
            $this->log->info("Count $latestYear Yr 11 folder codes: $countYear11");

            // We will assume we are in the T3-T4 period if we have more than 10 folderCodes, and > 90% of for year 11
            $reMap = $countFolderCodes > 10 && ($countYear11 / $countFolderCodes) > 0.9;

            $this->log->notice(($reMap ? '' : "NOT ") . "Remapping $latestYear Year 11 folder codes to Year 12");

            if ($reMap) {
                foreach ($folderCodes as $folderCode) {
                    $reMapped = preg_replace("/(\D?)11(\D+)/", '${1}12${2}', $folderCode, 1);
                    if ($reMapped !== $folderCode) {
                        $this->log->debug("Remapping '$folderCode' to '$reMapped'");
                        $reMappedFolderCodes[$latestYear][$folderCode] = $reMapped;
                        $sisSourceId                                   = $this->yearAndFolderCodeToSisSourceIdMap[$latestYear][$folderCode];
                        $folder                                        = $this->yearAndFolderCodeToFolderMap[$latestYear][$folderCode];
                        unset(
                            $this->yearAndFolderCodeToSisSourceIdMap[$latestYear][$folderCode],
                            $this->yearAndFolderCodeToFolderMap[$latestYear][$folderCode]
                        );
                        $this->yearAndFolderCodeToSisSourceIdMap[$latestYear][$reMapped] = $sisSourceId;
                        $this->yearAndFolderCodeToFolderMap[$latestYear][$reMapped]      = $folder;
                    } else {
                        $this->log->warning("NOT remapping '$folderCode'");
                    }
                }
            }
        }
        return $reMappedFolderCodes;
    }

    /**
     * Attempt to parse a year from a sisSourceId, eg:
     *
     * 'Y2014C09DRA'    => '2014'
     * 'xxxY2014C09DRA' => '2014'
     * 'xxx'            => null
     *
     * @param string $sisSourceId
     * @param string $termName
     * @return string|null
     */
    public function parseYearFromSisSourceId(string $sisSourceId, string $termName): ?string
    {
        if (preg_match("/^Y(\d{4})C.+$/", $sisSourceId, $canonicalYearMatch)) {
            // This is the canonical form.
            return $canonicalYearMatch[1];
        }
        if (preg_match("/(\d{4})/", $sisSourceId, $fuzzyYearMatch)) {
            // Fall back to any four digits
            return $fuzzyYearMatch[1];
        }
        return null;
    }


    /**
     * Attempt to parse a Subject Code from a sisSourceId, eg:
     *
     * 'Y2014C09DRANOT' => '09DRA'
     * 'Y2014C09DRA'    => '09DRA'
     * 'C09DRA'         => null
     *
     * @param string $sisSourceId
     * @return string|null
     */
    public function parseSubjectCodeFromSisSourceId(string $sisSourceId): ?string
    {
        // Parse subject code (exact match only, no fuzzy fall back for now)
        if (preg_match("/^Y\d{4}C(.+)$/", $sisSourceId, $canonicalSubjectMatch)) {
            // This is the canonical form.
            return preg_replace("/NOT$/", '', $canonicalSubjectMatch[1]);
        }
        return null;
    }
}
