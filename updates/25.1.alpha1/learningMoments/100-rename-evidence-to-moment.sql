-- Learning moments have mistakenly been called "evidence" this whole time,
-- since a learning moment is supported by having one or more pieces of evidence.
-- Tags exist for the learning moment, not the evidence that supports it.

rename table lu_evidence_tag to lu_moment_tag;

rename table evidence_tag to moment_tag;

rename table curriculum_evidence to curriculum_moment;

rename table evidence to moment;

alter table curriculum_moment
    change evidence_id moment_id int unsigned not null;

alter table moment_tag
    change evidence_id moment_id int unsigned not null;

