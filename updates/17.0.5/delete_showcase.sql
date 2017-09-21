UPDATE eportfolio_showcase_item SET deleted_at = now()
WHERE submission_box_id
      IN(
        SELECT id FROM submission_box
        WHERE deleted = 1
      );