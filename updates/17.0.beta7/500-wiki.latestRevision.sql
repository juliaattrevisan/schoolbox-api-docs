-- Wiki + WikiRevision relationship is complicated because of the FKs in both directions
-- Each wiki points to its latest revision, each revision points back to the wiki
-- One of these relationships must be optional so we can insert one entity before the other.
-- We will make the wiki to revision optional, as it is more conventional to insert a parent before a child.

-- This relationship must be optional so we can insert the wiki before its first revision.
ALTER TABLE wiki
  CHANGE COLUMN latestRevision latestRevision INT(11) UNSIGNED NULL COMMENT 'Nullable to enable insertion of wiki before first revision';

-- If a wiki's latestRevision points to a revision that doesn't exist,
-- and it has 1 or more revisions available ('ar'), set it to the latest available revision
UPDATE wiki w
  LEFT JOIN wikiRevision r ON r.revisionID = w.latestRevision
  JOIN wikiRevision ar ON ar.wikiID = w.wikiID
  JOIN (
         SELECT wikiID, MAX(created) as max_created
         FROM wikiRevision
         GROUP BY wikiID
       ) t ON t.wikiID = w.wikiID AND t.max_created = ar.created
SET w.latestRevision = ar.revisionID
WHERE r.revisionID IS NULL;

-- If the wiki has no revisions, it is unrecoverable. Delete the wiki
DELETE w FROM wiki w
  LEFT JOIN wikiRevision r ON w.latestRevision = r.revisionID
WHERE r.revisionID IS NULL;

-- Delete any revisions that don't belong to a Wiki, they are unrecoverable
DELETE r FROM wikiRevision r
  LEFT JOIN wiki w ON w.wikiID = r.wikiID
WHERE w.wikiID IS NULL;

-- Add FK from wiki-to-latestRevision.
-- Wiki.latestRevision is Nullable to enable insertion of wiki prior to first revision.
ALTER TABLE wiki
  ADD CONSTRAINT fk_wiki_latestRevision_wikiRevision_revisionID
FOREIGN KEY (latestRevision) REFERENCES wikiRevision(revisionID)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;

-- Upgrade revision-to-wiki from optional to mandatory
ALTER TABLE wikiRevision
  DROP FOREIGN KEY fk_wikiRevision_wiki_id;

ALTER TABLE wikiRevision
  CHANGE COLUMN wikiID wikiID INT(11) UNSIGNED NOT NULL;

ALTER TABLE wikiRevision
  ADD CONSTRAINT fk_wikiRevision_wiki_id
FOREIGN KEY (wikiID) REFERENCES wiki(wikiID)
  ON UPDATE RESTRICT
  ON DELETE RESTRICT;
