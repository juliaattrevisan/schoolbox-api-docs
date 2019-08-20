<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Psr\Log\LoggerInterface;

/**
 * Class Processor
 *
 * Determine which Profile Import files were passed, based on header contents,
 * and process them iteratively
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Processor
{

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @var array
     */
    private $files;


    /**
     * Processor constructor.
     *
     * @param LoggerInterface $logger
     * @param array           $files
     */
    public function __construct(LoggerInterface $logger, array $files)
    {
        $this->logger = $logger;
        $this->files = $files;
    }


    /**
     * Process each of the supplied files
     */
    public function process(): void
    {
        foreach ($this->files as $file) {
            $header = fgetcsv($file);

            if ($this->isRelation($header)) {

                /* Process the Relations csv
                 */
                $this->processRelation($file);
            } elseif ($this->isStudentProfile($header)) {

                /* Process the Student Profile csv
                 */
                $this->processStudentProfile($file);
            } elseif ($this->isStaffProfile($header)) {

                /* Process the Staff Profile csv
                 */
                $this->processStaffProfile($file);
            } elseif ($this->isParentProfile($header)) {

                /* Process the Parent Profile csv
                 */
                $this->processParentProfile($file);
            }
        }
    }


    /**
     * $row is the same as the expected columns of a Relations csv
     *
     * @param array $row
     * @return bool
     */
    private function isRelation(array $row): bool
    {
        return $this->matches($row, [
            'External ID',
            'Firstname',
            'Lastname',
        ]);
    }


    /**
     * Process the Relations csv
     *
     * @param $file
     */
    private function processRelation($file)
    {
    }


    /**
     * $row is the same as the expected columns of a Student Profile csv
     *
     * @param array $row
     * @return bool
     */
    private function isStudentProfile(array $row): bool
    {
        return $this->matches($row, [
            'External ID',
            'Firstname',
            'Lastname',
        ]);
    }


    /**
     * Process student profile csv
     *
     * @param $file
     */
    private function processStudentProfile($file)
    {
    }


    /**
     * $row is the same as the expected columns of a Staff Profile csv
     *
     * @param array $row
     * @return bool
     */
    private function isStaffProfile(array $row): bool
    {
        return $this->matches($row, [
            'External ID',
            'Firstname',
            'Lastname',
        ]);
    }


    /**
     * Process staff profile csv
     *
     * @param $file
     */
    private function processStaffProfile($file)
    {
    }


    /**
     * $row is the same as the expected columns of a Parent Profile csv
     *
     * @param array $row
     * @return bool
     */
    private function isParentProfile(array $row): bool
    {
        return $this->matches($row, [
            'External ID',
            'Firstname',
            'Lastname',
        ]);
    }


    /**
     * Process parent profile csv
     *
     * @param $file
     */
    private function processParentProfile($file)
    {
    }


    /**
     * The elements of $row match $expected exactly
     *
     * @param array $row
     * @param array $expected
     * @return bool
     */
    private function matches(array $row, array $expected): bool
    {
        return !\count(array_diff($row, $expected));
    }
}
