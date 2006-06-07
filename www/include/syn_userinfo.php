<?php 
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Function to get classlist of students from synergy. 	*
*   $Id: syn_classlist.php,v 1.6 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

function getStudentInfo($synID)
{
        //setup database
        $db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		mssql_select_db(MSSQL_db, $db);
        $query = "
		SELECT 	vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname, 
				vStudents.StudentTutor, vStudents.StudentYearLevel, vStudents.StudentBirthDate, 
				vStudents.StudentEmergencyName, vStudents.StudentEmergencyPhone, vStudents.StudentEmergencyPhoneAlt,
				contactDetails.StudentContactTitle, contactDetails.StudentContactPreferred, contactDetails.StudentContactSurname,
				contactDetails.StudentContactAddress1, contactDetails.StudentContactSuburb, contactDetails.StudentContactPostCode, contactDetails.StudentContactState,
				contactDetails.StudentContactPhoneActual, contactDetails.StudentContactMobilePhone, contactDetails.StudentContactOccupPhone,
				contactDetails.StudentContactSpouseTitle, contactDetails.StudentContactSpousePreferred, contactDetails.StudentContactSpouseSurname,
				contactDetails.StudentContactSpouseMobilePhone, contactDetails.StudentContactSpouseOccupPhone
		FROM 
				SynergyOne.dbo.vStudentContactAllAddress contactDetails, 
				SynergyOne.dbo.vStudents vStudents 
		WHERE 	vStudents.CurrentSemesterOnlyFlag = contactDetails.CurrentSemesterOnlyFlag 
			AND vStudents.FileSemester = contactDetails.FileSemester 
			AND vStudents.FileYear = contactDetails.FileYear 
			AND vStudents.StudentID = contactDetails.ID
			AND (contactDetails.StudentContactDescription='Contact 1' OR contactDetails.StudentContactDescription='Primary Contacts')
			AND vStudents.StudentID = $synID
		ORDER BY
			contactDetails.FileYear DESC,contactDetails.FileSemester DESC
			";
	$result = mssql_query($query, $db);
	return mssql_fetch_array($result);
}
?>
