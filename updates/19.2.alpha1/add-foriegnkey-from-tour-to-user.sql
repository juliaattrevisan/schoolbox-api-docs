-- Add foreign key to `user` table
ALTER TABLE `tour`
    ADD FOREIGN KEY (`author_user_id`)
    REFERENCES      `user` (`id`);
