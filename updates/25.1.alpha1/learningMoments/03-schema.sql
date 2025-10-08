alter table evidence
    add submitted_at datetime null after type;

alter table evidence
    add submitted_by int(10) unsigned null after submitted_at;

alter table evidence
    add published_at datetime null after submitted_by;

alter table evidence
    add published_by int(10) unsigned null after published_at;

alter table evidence
    add rejected_at datetime null after published_by;

alter table evidence
    add rejected_by int(10) unsigned null after rejected_at;

alter table evidence
    add constraint fk_evidence_published_by
        foreign key (published_by) references user (id);

alter table evidence
    add constraint fk_evidence_rejected_by
        foreign key (rejected_by) references user (id);

alter table evidence
    add constraint fk_evidence_submitted_by
        foreign key (submitted_by) references user (id);
