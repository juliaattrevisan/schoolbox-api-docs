ALTER TABLE ib_group_subjects
    MODIFY level ENUM ('HL', 'SL', 'EE', 'TK', 'MY') NULL COMMENT "Level is null for Creative, Activity, Service (CAS) as it doesn't have grading";

-- Adding a new column to store the subject name as it should appear on the transcript and used to send to API. This is necessary because some subjects have different names for display and for API purposes. For example, "Theory of Knowledge" is displayed as "Theory of Knowledge" but should be sent to the API as "THEORY KNOWL." to match the expected format.
ALTER TABLE lu_ib_subjects
    ADD COLUMN transcript_name VARCHAR(255) NULL COMMENT "The name of the subject as it should appear on the transcript and used to send to API. ie. THEORY KNOWL. instead of Theory of Knowledge" AFTER id;

-- Indexing the columns to improve query performance when searching
CREATE INDEX idx_lu_ib_subjects_transcript_name
    ON lu_ib_subjects (transcript_name);

CREATE INDEX idx_lu_ib_subjects_name
    ON lu_ib_subjects (name);


-- Updating the subjects table with the new subject names to match the subject list format
UPDATE lu_ib_subjects AS subjects
JOIN (
    SELECT 22 AS id, 'CHEMISTRY' AS transcript_name, 'CHEMISTRY' AS name
    UNION ALL SELECT 35, 'THEORY KNOWL.', NULL
    UNION ALL SELECT 27, 'SPORTS EX SCI ', 'SPORTS EXERCISE AND HEALTH SCIENCE '
    UNION ALL SELECT 20, 'WORLD RELIG.', 'WORLD RELIGIONS '
    UNION ALL SELECT 13, 'HISTORY AFRICA AND MIDDLE EAST ', 'HISTORY OF AFRICA AND MIDDLE EAST'
    UNION ALL SELECT 16, 'HISTORY EUROPE ', 'HISTORY EUROPE'
    UNION ALL SELECT 1, '[LANGUAGE] A: Literature ', 'LANGUAGE A: Literature'
    UNION ALL SELECT 18, 'PSYCHOLOGY', 'PSYCHOLOGY'
    UNION ALL SELECT 21, 'BIOLOGY', 'BIOLOGY'
    UNION ALL SELECT 24, 'DESIGN TECH.', 'DESIGN TECHOLOGY '
    UNION ALL SELECT 31, 'MUSIC', 'MUSIC'
    UNION ALL SELECT 12, 'HISTORY', 'HISTORY'
    UNION ALL SELECT 9, 'ECONOMICS', 'ECONOMICS'
    UNION ALL SELECT 30, 'DANCE', 'DANCE'
    UNION ALL SELECT 26, 'PHYSICS', 'PHYSICS'
    UNION ALL SELECT 28, 'MATHEMATICS ANALYSIS AND APPROACHES', 'MATHEMATICS ANALYSIS AND APPROACHES'
    UNION ALL SELECT 2, '[LANGUAGE] A: Lang and Literature', 'LANGUAGE A Language and Literature'
    UNION ALL SELECT 8, 'DIGITAL SOC.', 'DIGITAL SOCIETIES '
    UNION ALL SELECT 34, 'VISUAL ARTS ', 'VISUAL ARTS '
    UNION ALL SELECT 29, 'MATHEMATICS APPLICATIONS AND INTERP', 'MATHEMATICS APPLICATIONS AND INTERPRETATIONS'
    UNION ALL SELECT 5, '[LANGUAGE] AB.', 'LANGUAGE Ab Initio'
    UNION ALL SELECT 19, 'SOC.CUL.ANTH.', 'SOCIAL AND CULTURAL ANTHOPOLOGY '
    UNION ALL SELECT 6, '[LANGUAGE] B', 'LANGUAGE B'
    UNION ALL SELECT 10, 'GEOGRAPHY', 'GEOGRAPHY'
    UNION ALL SELECT 23, 'COMPUTER SC.', 'COMPUTER SCIENCE '
    UNION ALL SELECT 15, 'HISTORY ASIA AND OCEANIA', 'HISTORY ASIA AND OCEANIA'
    UNION ALL SELECT 25, 'ENV. AND SOC.', 'ENVIRONMENTAL SYSTEMS AND SOCIETIES'
    UNION ALL SELECT 11, 'GLOBAL POLITICS', 'GLOBAL POLITICS'
    UNION ALL SELECT 4, 'CLASS.[LANGUAGE]', 'CLASSICAL LANGUAGE'
    UNION ALL SELECT 32, 'FILM', 'FILM'
    UNION ALL SELECT 3, 'LITERATURE AND PERFORMANCE', 'Literature and Performance'
    UNION ALL SELECT 14, 'HISTORY AMERICAS', 'HISTORY AMERICAS '
    UNION ALL SELECT 17, 'PHILOSOPHY ', 'PHILOSOPHY'
    UNION ALL SELECT 33, 'THEATRE', 'THEATRE'
    UNION ALL SELECT 7, 'BUSINESS MANAGEMENT', 'BUSINESS MANAGEMENT'
) AS subject_names ON subject_names.id = subjects.id
SET subjects.transcript_name = subject_names.transcript_name,
    subjects.name = COALESCE(subject_names.name, subjects.name)
WHERE subjects.id = subject_names.id;


