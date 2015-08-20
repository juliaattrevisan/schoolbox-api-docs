<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Parent->parse()`.
 *
 * Converts results to the same format that
 * `External_UserInterface->getStudentUserAccountInfo()` returns.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Parent_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
{
    /**
     * @return array
     */
    public function current()
    {
        /** @var Alaress\Xml\State $current */
        $current = $this->getInnerIterator()->current();
        $result  = new SimpleXMLElement($current->getReader()->readOuterXml());
        $id      = (string)$result->attributes()->id;
        if ($current->matches('//PupilManager/Contacts/Contact')) {
            // iSAMS contains no home phone
            return [
                'RecordType'                  => 'Contacts',
                'ID'                          => $id,
                'StudentContactID'            => $id,
                // getParentUserAccountInfo fields
                'StudentContactTitle'         => "$result->Title",
                'StudentContactPreferred'     => "$result->Forename",
                'StudentContactSurname'       => "$result->Surname",
                'StudentContactEmail'         => "$result->EmailAddress",
                // Derive student ID from these
                'StudentContactStudentIds'    => $this->parseStudents($result->Pupils[0]),
                // getStudentContactList fields
                'StudentContactPhoneActual'   => '',
                'StudentContactOccupPhone'    => "$result->Telephone",
                'StudentContactMobilePhone'   => "$result->Mobile",
                'StudentContactRelation'      => "$result->RelationshipRaw",
                'StudentContactType'          => "$result->Relationship",
                'StudentContactDefaultEmail'  => "$result->EmailAddress",
            ] + $this->parseAddress($result->Address[0]);
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }

    /**
     * Parses the addresses in an Address element into a Schoolbox format.
     *
     * @param  SimpleXMLElement $address
     * @return array
     */
    protected function parseAddress($address)
    {
        return [
            'StudentContactAddress1' => (string)$address->AddressLines->Address1,
            'StudentContactAddress2' => (string)$address->AddressLines->Address2,
            'StudentContactAddress3' => (string)$address->AddressLines->Address3,
            'StudentContactPostCode' => (string)$address->Postcode,
            'StudentContactSuburb'   => (string)$address->Town,
            'StudentContactState'    => (string)$address->County,
            'StudentContactCountry'  => (string)$address->Country,
        ];
    }

    /**
     * Parses the student list in a Pupils element into a list of student IDs.
     *
     * @param  SimpleXMLElement $students
     * @return array
     */
    protected function parseStudents($students)
    {
        $studentIds = [];
        if ($students instanceof SimpleXMLElement) {
            foreach ($students->children() as $student) {
                if (strtolower("$student->Status") === 'current') {
                    $studentIds[] = (int)$student->attributes()->id;
                }
            }
        }
        return $studentIds;
    }
}
