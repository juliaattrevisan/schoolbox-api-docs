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
                'RecordType'    => 'Contacts',
                'ID'            => $id,
                // getParentUserAccountInfo fields
                'Title'         => $result['Title'],
                'Preferred'     => $result['Forename'],
                'Surname'       => $result['Surname'],
                'Email'         => $result['EmailAddress'],
                'StudentIds'    => $this->parseStudents($result['Pupils']),
                // getStudentContactList fields
                'PhoneActual'   => '',
                'OccupPhone'    => $result['Telephone'],
                'MobilePhone'   => $result['Mobile'],
                'Relation'      => $result['RelationshipRaw'],
                'DefaultEmail'  => $result['EmailAddress'],
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
            'Address1' => $address['AddressLines']['Address1'],
            'Address2' => $address['AddressLines']['Address2'],
            'Address3' => $address['AddressLines']['Address3'],
            'PostCode' => $address['Postcode'],
            'Suburb'   => $address['Town'],
            'State'    => $address['County'],
            'Country'  => $address['Country'],
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
                $studentIds[] = $student['attributes']['id'];
            }
        }
        return $studentIds;
    }
}
