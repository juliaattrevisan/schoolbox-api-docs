INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('video_bitrate', '512', 'Video Encoding Bitrate', 'The bitrate used when re-encoding uploaded videos (default: 512)', 4, '512:768:1024:2048', 4, 'true', 'false', 'true');

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('audio_bitrate', '96', 'Audio Encoding Bitrate', 'The bitrate used when re-encoding uploaded videos (default: 96)', 4, '96:128:164', 4, 'true', 'false', 'true');