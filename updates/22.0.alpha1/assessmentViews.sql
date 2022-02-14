create table assessment_view
(
    assessment_id  int unsigned      null,
    participant_id int(4) unsigned      null,
    user_id        int(4) unsigned      null,
    last_visit     datetime null,
    constraint assessment_view_assessment_id_fk
        foreign key (assessment_id) references assessment (id),
    constraint assessment_view_participant_fk
        foreign key (participant_id) references user (id),
    constraint assessment_view_user_id_fk
        foreign key (user_id) references user (id)
);

