/**
 * Discussion threads.
 *
 * Related to a comms article via a FK on the article table.
 */
CREATE TABLE discussion_thread (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    open BOOL NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/**
 * Posts on discussion threads.
 */
CREATE TABLE discussion_post (
    id INT(11) NOT NULL AUTO_INCREMENT,
    discussion_thread_id INT(11) UNSIGNED NOT NULL,
    author_id INT(11) UNSIGNED NOT NULL,
    content TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY fk_discussion_post_discussion_thread_id (discussion_thread_id),
    KEY fk_discussion_post_author_id (author_id),
    CONSTRAINT fk_discussion_post_discussion_thread_id
        FOREIGN KEY (discussion_thread_id) REFERENCES discussion_thread (id)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION,
    CONSTRAINT fk_discussion_post_author_id
        FOREIGN KEY (author_id) REFERENCES user (id)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/**
 * Add the relation to the comms article table
 */
ALTER TABLE comms_news_article
    ADD COLUMN discussion_thread_id INT(11) UNSIGNED NULL DEFAULT NULL AFTER feature_image_hash,
    ADD INDEX fk_comms_news_article_discussion_thread_id (discussion_thread_id ASC);
ALTER TABLE comms_news_article
    ADD CONSTRAINT fk_comms_news_article_discussion_thread_id
        FOREIGN KEY (discussion_thread_id) REFERENCES discussion_thread (id)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
