CREATE TABLE discussion_thread (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    open BOOL NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE discussion_thread_context (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    discussion_thread_id INT(11) UNSIGNED NOT NULL,
    context_type ENUM('news'),
    context_id INT(11) UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY fk_discussion_thread_context_discussion_thread_id (discussion_thread_id),
    UNIQUE KEY fk_discussion_thread_context_identifier (context_type, context_id),
    CONSTRAINT fk_discussion_thread_context_discussion_thread_id
        FOREIGN KEY (discussion_thread_id) REFERENCES discussion_thread (id)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
