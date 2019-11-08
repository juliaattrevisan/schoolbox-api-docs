<?php
// viewer, viewee, visibility
use Schoolbox\Role;
use Schoolbox\User;

// viewer, viewee, visibility
$studentRole = (new Role())->setType(Role::STUDENT);
$staffRole   = (new Role())->setType(Role::STAFF);
$parentRole  = (new Role())->setType(Role::PARENT);
$guestRole   = (new Role())->setType(Role::GUEST);

$superUser1 = (new User())->setId(1)->setFirstname('Super user 1')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
$staff1     = (new User())->setId(2)->setFirstname('Test Staff 1')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
$student1   = (new User())->setId(3)->setFirstname('Test Student 1')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
$parent1    = (new User())->setId(4)->setFirstname('Test Parent 1')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-')
                          ->setExternalId('parent1');
$guest1     = (new User())->setId(5)->setFirstname('Test Guest 1')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');
$superUser2 = (new User())->setId(6)->setFirstname('Super user 2')->setSuperuser(true)->setRole($staffRole)->setAltEmail('-');
$staff2     = (new User())->setId(7)->setFirstname('Test Staff 2')->setSuperuser(false)->setRole($staffRole)->setAltEmail('-');
$student2   = (new User())->setId(8)->setFirstname('Test Student 2')->setSuperuser(false)->setRole($studentRole)->setAltEmail('-');
$parent2    = (new User())->setId(9)->setFirstname('Test Parent 2')->setSuperuser(false)->setRole($parentRole)->setAltEmail('-');
$guest2     = (new User())->setId(10)->setFirstname('Test Guest 2')->setSuperuser(false)->setRole($guestRole)->setAltEmail('-');

$viewers = $viewees = [
    '$student1' => $student1,
    '$student2' => $student2,
    '$parent1' => $parent1,
    '$parent2' => $parent2,
    '$superUser1' => $superUser1,
    '$superUser2' => $superUser2,
    '$staff1' => $staff1,
    '$staff2' => $staff2,
    '$guest1' => $guest1,
    '$guest2' => $guest2,
];

$configOptions = [
    'true' => true,
    'false' => false
];

$config = $this->getDi()->get('config');

$rows = [];
$rows[] = "viewee, viewer, restrictParentEmails, reportStaffAccess";

$outcomes = [];
$outcomes[] = 'ValidDetailsVisible';

// every viewer meets every viewee, including meeting themselves
foreach ($viewees as $vieweeKey => $viewee) {
    foreach ($viewers as $viewerKey => $viewer) {

        // ReportStaffAccess config option
        foreach ($configOptions as $reportStaffAccessKey => $reportStaffAccess) {

            // RestrictParentEmails config option
            foreach ($configOptions as $restrictParentEmailsKey => $restrictParentEmails) {

                // Modified the signature of isSatisfiedBy() so that it didn't rely on the current
                // configuration values to speculatively explore the possible outcomes
                $outcome = (new \Schoolbox\Profile\ValidDetailsVisible(new \External_User(), $this->getDi()->get('config')))
                    ->isSatisfiedBy(
                        $viewee,
                        $viewer,
                        $reportStaffAccess,
                        $restrictParentEmails
                    );

                $outcomes[] = $outcome;

                if ($outcome) {
                    $result = 'true';
                } else {
                    $result = 'false';
                }

                $row = "[$vieweeKey, $viewerKey, $restrictParentEmailsKey, $reportStaffAccessKey, $result],";
                $rows[] = $row;
            }
        }
    }
}

new \Alaress_Probe($rows, $outcomes, implode("<BR>", $rows));

die();
