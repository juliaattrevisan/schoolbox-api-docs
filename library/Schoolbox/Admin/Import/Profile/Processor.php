<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Csv\Reader;
use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage\IStorage;

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
     * @var array
     */
    private $files;


    /**
     * Processor constructor.
     *
     * @param array           $files
     */
    public function __construct(array $files)
    {
        $this->files = $files;
    }


    /**
     * Process each of the supplied files
     * @throws \RuntimeException
     */
    public function process(): void
    {
        foreach ($this->files as $file) {

            $reader = new Reader($file->readStream());
            $reader->setLoadFromFirst(false);

            /* Load the first row
             */
            $reader->next();

            /* Get the columns out and from here, work out what kind of file it is
             */
            $header = $reader->current();
//                new \Alaress_Probe($file, $header);
//                die();


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
                $this->processStaffProfile($reader);
            } elseif ($this->isParentProfile($header)) {

                /* Process the Parent Profile csv
                 */
                $this->processParentProfile($reader);
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
    private function processRelation(IStorage $file)
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
            'Type',
            'Username',
            'Title',
            'Firstname',
            'Surname',
            'Email',
            'Work Phone',
            'Staff Category',
            'Position Title',
            'Department',
            'Building',
            'Room',
            'Campus'
        ]);
    }


    /**
     * Process staff profile csv
     *
     * @param Reader $reader
     */
    private function processStaffProfile(Reader $reader): void
    {
        foreach ($reader as $i => $row) {
            new \Alaress_Probe($row);
        }
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
            'Type',
            'Username',
            'Title',
            'Firstname',
            'Surname',
            'Email',
            'Mobile',
            'Home Phone',
            'Work Phone'
        ]);
    }


    /**
     * Process parent profile csv
     *
     * @param Reader $reader
     */
    private function processParentProfile(Reader $reader): void
    {
        foreach ($reader as $i => $row) {
            new \Alaress_Probe($row);
        }
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
