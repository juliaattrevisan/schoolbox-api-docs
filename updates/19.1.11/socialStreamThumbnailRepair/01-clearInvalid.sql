-- Clear known broken thumbnails
UPDATE socialstream_post SET thumbnail = '' WHERE thumbnail IN ('icon:folder.png', 'icon:home.png');
