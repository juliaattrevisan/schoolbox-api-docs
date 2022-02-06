AlTER TABLE package
    ADD hidden enum ('true', 'false') default 'false';

INSERT INTO package (title, description, location, hidden, installed) VALUES
('IB PYP Programme', 'Attaches codes to the IB PYP Programme to allow for the mapping of these to learning resources.', 'IBPYPProgramme', 'true', 0),
('IB MYP Programme', 'Attaches codes to the IB MYP Programme to allow for the mapping of these to learning resources.', 'IBMYPProgramme', 'true', 0),
('IB Programme', 'Attaches codes to the IB Programme to allow for the mapping of these to learning resources.', 'IBProgramme', 'true', 0);

