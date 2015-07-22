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
        $current = $this->getInnerIterator()->current();
        $result  = $this->stripClarkNotationFromArrayKeys($current['value']);

        // ID: the database's PK value, distinct from SchoolID
        $id = $current['attributes']['id'];
        if (array_key_exists('Address', $result)) {
            // //Contacts/Contact: build student's contacts out of this
            // iSAMS contains no home phone
            return [
                'RecordType'                  => 'Contacts',
                'ID'                          => $id,
                'StudentContactID'            => $id,
                // getParentUserAccountInfo fields
                'StudentContactTitle'         => $result['Title'],
                'StudentContactPreferred'     => $result['Forename'],
                'StudentContactSurname'       => $result['Surname'],
                'StudentContactEmail'         => $result['EmailAddress'],
                // Derive student ID from these
                'StudentContactStudentIds'    => $this->parseStudents($result['Pupils']),
                // getStudentContactList fields
                'StudentContactPhoneActual'   => '',
                'StudentContactOccupPhone'    => $result['Telephone'],
                'StudentContactMobilePhone'   => $result['Mobile'],
                'StudentContactRelation'      => $result['RelationshipRaw'],
                'StudentContactDefaultEmail'  => $result['EmailAddress'],
            ] + $this->parseAddress($result['Address']);
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
     * @param  array $address
     * @return array
     */
    protected function parseAddress($address)
    {
        $address                 = $this->stripClarkNotationFromArrayKeys($address);
        $address['AddressLines'] = $this->stripClarkNotationFromArrayKeys($address['AddressLines']);
        return [
            'StudentContactAddress1' => $address['AddressLines']['Address1'],
            'StudentContactAddress2' => $address['AddressLines']['Address2'],
            'StudentContactAddress3' => $address['AddressLines']['Address3'],
            'StudentContactPostCode' => $address['Postcode'],
            'StudentContactSuburb'   => $address['Town'],
            'StudentContactState'    => $address['County'],
            'StudentContactCountry'  => $address['Country'],
        ];
    }

    /**
     * Parses the student list in a Pupils element into a list of student IDs.
     *
     * @param  array $students
     * @return array
     */
    protected function parseStudents($students)
    {
        $studentIds = [];
        foreach ($students as $student) {
            $values = $this->stripClarkNotationFromArrayKeys($student['value']);
            if (strtolower($values['Status']) === 'current') {
                // SchoolId is a legacy ID, according to documentation
                $studentIds[] = $values['SchoolId'];
            }
        }
        return $studentIds;
    }
}
