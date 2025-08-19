-- Learning moments have mistakenly been called "evidence" this whole time,
-- since a learning moment is supported by having one or more pieces of evidence.
-- Tags exist for the learning moment, not the evidence that supports it.

rename table lu_evidence_tag to lu_moment_tag;

rename table evidence_tag to moment_tag;
