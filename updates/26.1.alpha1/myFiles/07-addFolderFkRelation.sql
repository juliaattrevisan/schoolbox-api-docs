ALTER TABLE myFolder
ADD CONSTRAINT myFolder_myFolder_id_fk
FOREIGN KEY (parent) REFERENCES myFolder (id);
