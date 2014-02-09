INSERT INTO test (id, test) VALUES (1, 'testing');

--//@UNDO

DELETE FROM test where id = 1 and test = 'testing';
