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

INSERT INTO package (title,description,location, installed) VALUES
    ('Australian Curriculum - General Capabilities',
     'Attaches codes to the Australian Curriculum General Capabilities, to allow for the mapping of these to learning resources.  This is required to install the General Capability Badge set.',
     'AusGeneralCapabilities',
     0),
    ('Australian Curriculum - Cross Curricular Priorities',
     'Attaches codes to the Australian Curriculum Cross-curriculum priorities, to allow for the mapping of these to learning resources.',
     'AusCrossCurricular',
     0),
    ('General Capability Badges',
     'Over 300 badges linked to the Australian Curriculum General Capabilities, including Critical and Creating Thinking, ICT, Literacy and Numeracy. The General Capabilities package must be installed for these badges to be installed.',
     'AusGeneralCapabilitiesBadge',
     0),
    ('Personal Development Badges',
     'A badge set with codes attached to the Australian Curriculum Personal and Social Capability, and Intercultural and Ethical Understandings, to allow you track the personal development of students. The General Capabilities package must be installed for these badges to be installed.',
     'AusPersonalDevelopmentBadge',
     0),
    ('Extra Curricular Badges',
     'A base badge set for some extra-curricular activities, to allow you to celebrate the achievements of students outside the classroom.',
     'ExtraCurricularBadge',
     0),
    ('Community Badges',
     'A base badge set for recognising the community engagement achievements of students.',
     'CommunityBadge',
     0);


