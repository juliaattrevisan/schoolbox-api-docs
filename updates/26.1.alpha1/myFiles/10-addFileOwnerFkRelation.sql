ALTER TABLE myFile
ADD CONSTRAINT myFile_user_id_fk
FOREIGN KEY (owner) REFERENCES user (id);
