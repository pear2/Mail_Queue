CREATE TABLE mail_queue (
id INT NOT NULL default 0 PRIMARY KEY,
create_time timestamp NOT NULL default '1970-01-01 00:00:00',
time_to_send timestamp NOT NULL default '1970-01-01 00:00:00',
sent_time timestamp default NULL,
id_user INT NOT NULL default 0,
ip varchar(20) NOT NULL default 'unknown',
sender varchar(50) NOT NULL default '',
recipient text NOT NULL,
headers text,
body text,
try_sent INT NOT NULL default 0,
delete_after_send INT NOT NULL default 1
);

CREATE INDEX mailq_time_to_send ON mail_queue (time_to_send);
CREATE INDEX mailq_id_user ON mail_queue (id_user);
