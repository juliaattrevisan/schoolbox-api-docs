-- All CMS redirects are deleted now, and will be recreated
DELETE FROM msg_redirect WHERE `from` LIKE '%cms.#';
