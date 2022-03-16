# Geosnapshot
Geosnapshot is a photo library with AI-driven face detection so you can find a set of pictures by face rather than the traditional searching of keywords.

## Setup
For Geosnapshot to work, you need to know about these pieces of the puzzle.

1. A Geosnapshot API Key.
2. The Geosnapshot Module needs to be enabled in admin.
3. Your SIS must support redaction.
4. Students must be current (it won't work with non-current students). This is implemented on a per-connector basis (for example, live Synergetic). Every connector will implement redaction uniquely.

## Redaction
Some students don't want/can't have their face made public. Geosnapshot Redactions is a means by which schools can explicitly prevent someone's face from matching a search request.

To do it, Schoolbox interrogates the SIS and gets a list of students and their redaction status according to the SIS. This list is compiled into a Geosnapshot-compatible payload before being sent to them, at which point they take over the handling of that data. This payload is essentially a complete list of students with a `1` or `0` for their redaction status (`1` meaning the student's face is redacted). (There's other data here too.)

> The redaction list consists of **current students** only. 
> 
> At the time of writing, only Synergetic supports redactions explicitly;  students need to be in the current `FileYear` and `FileSemester`.

For Geosnapshot to redact someone from a search result, they need a sample picture of that person's face. Instead of having the entire fleet transmit the pictures en masse, Geosnapshot is intending to visit the instances themselves and grab the picture programmatically.

If we transmitted the files:
* we end up having to micromanage the redaction process more than we really need since we would be also responsible for the file getting into Geosnapshot's system
* Geosnapshot would have needed to build more endpoints for us to put binary data
* we'll probably end up ddos'ing Geosnapshot at some point

Therefore, in the payloads we send to Geosnapshot, we also provide a url to a sample picture of that person's face. After we send that payload, Geosnapshot then visits the aforementioned url and grabs the picture's binary contents so it can be put into their system.

### Connector Support
Only specific SIS will support redaction. At the time of writing, only the following connectors support Redaction:
* Schoolbox (and by extension, SUDS)
* Synergetic

Other connectors aren't known to support redaction data in a predictable manner; TASS can do it through non-standard user-defined custom fields, for example.

### Payloads
Each connector that supports redaction generates data via the method `getPhotoRedactionList()`. This compiles a list of humans and their interpreted redaction status in accordance to the SIS. Every connector will produce a similar/synonymous outcome uniquely.

This redaction list is later reorganised into a Geosnapshot-compatible payload via `RedactedPhotoList`. The url to the person's profile picture is shown here along with extra metadata about when that picture was last modified; this allows Geosnapshot to decide whether to fetch the contents (since it is newer than Geosnapshot's current version) or to disregard it for now (since Geosnapshot already has this version, or a newer one). This allows Geosnapshot to minimise their accesses to the fleet's instances too.

#### Transmission and CLI Tool
Payload transmission is controlled via a cli tool `/tools/geosnapshot/redaction.php`. It's also set up in crontabs under the title `Geosnapshot Redactions`

### Image Retrieval
All our instances are protected by login requirements which would normally preclude Geosnapshot (or anyone else) from getting data.

To avoid it and allow Geosnapshot to fetch the student's profile pictures, we've implemented a basic authentication requirement: Geosnapshot needs to make a request with a header token to a specific endpoint that we've crafted for them.

The header token is stored in a config var `geosnapshot_api_key`. This is the same key we use to authenticate to geosnapshot, it is unique per school and is setup during provisioning. The header token must be sent in the header `X-API-KEY`.

The request is made to the Geosnapshot-specific version of `portrait.php` located in `/geosnapshot/portrait.php` which will serve up a profile picture if the `X-API-KEY` of the Geosnapshot request matches the `geosnapshot_redaction_key` stored in config.

An example curl request:

```
curl -X GET \
-L \
-o image.jpg \
-H "X-API-KEY: a3Gsmhd2l4nsfhjlj2590" \
"https://vagrant.dev.schoolbox.cloud/geosnapshot/portrait.php?id=1&size=constrain200"
```

### Algorithmic Summary
* We compile a list of students with their redaction status
* Each student comes with metadata about their profile picture - a url to the picture itself and when that was last modified
* We send this list to Geosnapshot
* Geosnapshot decides whose pictures need updating
* Geosnapshot goes and fetches those pictures where necessary, in their own way and in their own time
* This process repeats periodically

