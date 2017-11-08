# Pastoral Care Config Settings

[Back](./README.MD)

## TLDR

Most of these settings should be accessible from the 'Pastoral Care' setings tab in the admin system 
[http://192.168.36.10/adminv2/setting/Pastoral+Care](http://192.168.36.10/adminv2/setting/Pastoral+Care)

## Module Settings

##### pastoral_enabled

Enables/disables the entire pastoral care module. This should be updated by Puppet based on the schools licence.

##### pastoral_severity

If enabled pastoral severity will appear in the system. If disabled there should be no reference to severity anywhere
in the pastoral care system

## External Syncing

##### external_pastoral

If enabled pastoral care records will be uploaded to the schools external SIS (Synergtic only at present).

##### external_pastoral_upload

If enabled pastoral care records documents will be uploaded to the schools external SIS (Synergetic Docman only at 
present). 

##### pastoral_external_delete

If enabled pastoral care records will be deleted from the external SIS when the related record is removed from
Schoolbox.

##### pastoral_upload_timeout

This is the maximum upload time allowed when uploading attachments to the external SIS. The upload process is
notoriously slow (the file has to be encoded as an ASCII hex string which greatly increases the size of the transfer,
so this value should not be set too low)

## Permissions

##### pastoral_parent_access

If enabled parents will be able to access their students pastoral care page. If disabled parents should see no part
of the pastoral care mdule.

##### pastoral_individual_access

If enabled staff should be able to audience pastoral care items to individual users. If disabled only groups and roles
should be the only allowed audience selections.

## Display Settings

##### pastoral_page_size

This is the number of records to display per page on a students pastoral care page 

##### pastoral_page_visible

This is the maximum number of pagination buttons to display on the students pastoral care page
