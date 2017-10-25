
-- This creates a copy of the myFile in the student directory
INSERT INTO myFile
SELECT null, 0, ep.uid, mf.realname, mf.submitted, mf.description, mf.published, mf.hash, mf.deleted_at
FROM eportfolio_component_filelist_item ecfi
join eportfolio_page ep on ep.page_id = ecfi.page_id
join myFile mf on mf.id = ecfi.file_id where ep.uid != mf.owner;

-- This updates the filelist item to point at the new copy
UPDATE eportfolio_component_filelist_item as ecfi
join eportfolio_page ep on ep.page_id = ecfi.page_id
join myFile mf on mf.id = ecfi.file_id
set ecfi.file_id = (

SELECT mf2.id
from (select * from eportfolio_component_filelist_item) as ecfi2
join eportfolio_page ep on ep.page_id = ecfi2.page_id
join myFile mf1 on mf1.id = ecfi2.file_id
join myFile mf2 on mf1.hash = mf2.hash
where mf1.owner != mf2.owner
and (mf1.hash is not null and mf1.hash not like (''))
and mf2.owner = ep.uid
and ecfi2.id = ecfi.id

)
where ep.uid != mf.owner ;