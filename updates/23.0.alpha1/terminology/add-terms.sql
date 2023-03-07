INSERT INTO localisation (term, title, singular_form, plural_form, capitalise_first, indefinite_article) VALUES
("tutor"               , "Tutor"                 , "Tutor"                 , "Tutors"                 , 1, "a"),
("tutorGroup"          , "Tutor Group"           , "tutor group"           , "tutor groups"           , 1, "a"),
("yearLevelCoordinator", "Year Level Coordinator", "Year Level Coordinator", "Year Level Coordinators", 1, "a"),
("headOfHouse"         , "Head of House"         , "Head of House"         , "Heads of Houses"        , 1, "a");




# SQL commands targeting particular schools.  These will have to be modified somehow to make them work as intended.
#
# UPDATE localisation SET tutorGroup='NESA Number' WHERE ??='cccl';
#
# UPDATE localisation SET headOfHouse='Staff House Leader' WHERE ??='dsfg';
#
# UPDATE localisation SET tutorTeacher='Mentor' WHERE ??='ec';
#
# UPDATE localisation SET tutorTeacher='Holme Group Teacher' WHERE ??='fct';
# UPDATE localisation SET tutorGroup  ='Holme Group'         WHERE ??='fct';
#
# UPDATE localisation SET tutorGroup='Head of Year'      WHERE ??='kgsl';
# UPDATE localisation SET tutorGroup='Dean of Wellbeing' WHERE ??='kgsl';
#
# UPDATE localisation SET tutorTeacher='Academic Care Coordinator'       WHERE ??='lk';
# UPDATE localisation SET coordTeacher='Year Wellbeing Coordinator'      WHERE ??='lk';
# UPDATE localisation SET headOfHouse ='House Academic Care Coordinator' WHERE ??='lk';
# UPDATE localisation SET tutorGroup  ='Circle Class'                    WHERE ??='lk';
#
# UPDATE localisation SET tutorTeacher='Mentor'       WHERE ??='newic';
# UPDATE localisation SET tutorGroup  ='Mentor Group' WHERE ??='newic';
#
# UPDATE localisation SET tutorTeacher='Learning Mentor' WHERE ??='scg';
#
# UPDATE localisation SET tutorGroup='PC Group' WHERE ??='shalcc';
#
# UPDATE localisation SET tutorTeacher='Connect Teacher' WHERE ??='wsl';
# UPDATE localisation SET tutorGroup  ='Connect Group'   WHERE ??='wsl';
