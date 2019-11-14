-- Set tour.author_user_id to NULL if it contains a value that is not in user.id
UPDATE `tour`
SET `author_user_id` = null
WHERE `author_user_id` NOT IN (SELECT `id` FROM `user`);

-- Add foreign key to `user` table
ALTER TABLE `tour`
    ADD CONSTRAINT `fk_tour_author_user_id` FOREIGN KEY (`author_user_id`)
        REFERENCES `user` (`id`);
