-- Create table that links videos with their thumbnail/poster in OTFS storage

CREATE TABLE `video`
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  video_hash VARCHAR(100) CHARSET ASCII NOT NULL,
  poster_hash VARCHAR(100) CHARSET ASCII NULL,
  thumbnail_hash VARCHAR(100) CHARSET ASCII NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL
)
  ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX `video_hash_index` ON `video` (`video_hash`);

ALTER TABLE `video` ADD CONSTRAINT `fk_video_hash` FOREIGN KEY (`video_hash`) REFERENCES `storage`(`hash`);
ALTER TABLE `video` ADD CONSTRAINT `fk_poster_hash` FOREIGN KEY (`poster_hash`) REFERENCES `storage`(`hash`);
ALTER TABLE `video` ADD CONSTRAINT `fk_thumbnail_hash` FOREIGN KEY (`thumbnail_hash`) REFERENCES `storage`(`hash`);
