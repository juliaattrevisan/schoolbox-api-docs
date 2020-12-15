create table package
(
    id int unsigned auto_increment,
    title varchar(250) not null,
    description text null,
    location varchar(250) not null,
    installed bool null,
    constraint package_pk
        primary key (id)
)
    comment 'Contains packages that are available for install';

INSERT INTO package (title,description,location, installed) VALUES ('Australian Curriculum - General Capabilities', 'Provides Australian curriculum general capabilities with codes so they can be linked with other learning resources. This is required to install the primary general capability badges.', 'AusGeneralCapabilities', 0),
                                                        ('Australian Curriculum - Cross Curricular Priorities', 'Provides the cross curricular priorities to the curriculum mapping system, to ensure you can link your learning resources to these priorities.', 'AusCrossCurricular', 0),
                                                        ('Primary General Capability Badges', 'Over 300 badges linked to the Australian Curriculum General Capabilities.  Covers Critical and Creating Thinking, ICT, Literacy and Numeracy.', 'AusPrimaryCapabilitiesBadge', 0);
