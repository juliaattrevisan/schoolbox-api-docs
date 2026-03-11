ALTER TABLE myFile
ADD CONSTRAINT myFile_myFolder_id_fk
FOREIGN KEY (parent) REFERENCES myFolder (id);
