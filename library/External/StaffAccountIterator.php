<?php
/**
 * Iterator over a `External_UserInterface->getStaffUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Staff` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\Normalise\User\Common;

/**
 * @method SeekableIterator getInnerIterator()
 */
class External_StaffAccountIterator extends IteratorIterator implements SeekableIterator
{
    /**
     * @var resource
     */
    protected $result;

    /**
     * @var array
     */
    protected $currentRow = [];

    /**
     * @var int
     */
    protected $rowNum = 0;

    /**
     * Constructor
     *
     * @param SeekableIterator $iterator
     */
    public function __construct(SeekableIterator $iterator)
    {
        parent::__construct($iterator);
    }

    /**
     * Return the current element
     *
     * @return array
     */
    public function current()
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getStaffUserAccountInfo)
        $currentResult = $this->getInnerIterator()->current();
        $staff         = new Schoolbox\External\Normalise\User\Staff();
        $staff
            ->setId($currentResult['StaffID'])
            ->setTitle($currentResult['StaffTitle'])
            ->setPreferredName($currentResult['StaffPreferredName'])
            ->setLastname($currentResult['StaffSurname'])
            ->getMeta()
            ->setDepartment($currentResult['StaffDepartment'])
        ;

        $staff
            ->getMeta()
            ->getCategory()
            ->setType($currentResult['StaffCategoryType'])
        ;

        /* Account
         */
        $staff
            ->getAccount()
            ->setLogin($currentResult['NetworkLogin'])
            ->setActive($currentResult['ActiveFlag'])
        ;

        /* Email
         */
        $email1 = new Common\Email();
        $email1
            ->setAddress($currentResult['Email'])
        ;

        $email2 = new Common\Email();
        $email2
            ->setAddress($currentResult['OccupEmail'])
        ;

        foreach (array($email1, $email2) as $currentEmail) {
            /** @var Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $staff->addEmail($currentEmail);
            }
        }

        /* Campus
         */
        $campus = new Common\Campus();
        $campus
            ->setName($currentResult['StaffCampus'])
        ;

        $staff
            ->getCampus()
            ->add($campus)
        ;
        return $staff;
    }

    /**
     * Seeks to a position
     *
     * @var int $position
     */
    public function seek($position)
    {
        $this->getInnerIterator()->seek($position);
    }
}
