# OOP PHP Code Snippet
Example of OOP PHP code snippet. 

# Database Design
mysql> show tables;
+----------------------+
| Tables_in_wedding    |
+----------------------+
| attendees            |
| bish_tracker         |
| daily_weather_report |
| parties              |
| party_details        |
| quiz_scores          |
| test                 |
| users                |
+----------------------+
8 rows in set (0.00 sec)

mysql> desc parties;
+-----------------+--------------+------+-----+---------+-------+
| Field           | Type         | Null | Key | Default | Extra |
+-----------------+--------------+------+-----+---------+-------+
| party_id        | binary(16)   | NO   | PRI | NULL    |       |
| party_name      | varchar(255) | YES  |     | NULL    |       |
| is_admin        | tinyint(1)   | NO   |     | 0       |       |
| access_code     | varchar(5)   | NO   |     | NULL    |       |
| rsvp_access     | tinyint(1)   | NO   |     | 0       |       |
| party_attending | tinyint(1)   | YES  |     | NULL    |       |
| plus_one_option | tinyint      | YES  |     | 0       |       |
+-----------------+--------------+------+-----+---------+-------+
7 rows in set (0.01 sec)

mysql> desc party_details;
+------------------------------------+------------------+------+-----+---------+-------+
| Field                              | Type             | Null | Key | Default | Extra |
+------------------------------------+------------------+------+-----+---------+-------+
| party_id                           | binary(16)       | NO   | PRI | NULL    |       |
| language                           | enum('en','sp')  | NO   |     | NULL    |       |
| party_attending_count              | tinyint unsigned | YES  |     | NULL    |       |
| party_diet_restriction             | enum('y','n')    | YES  |     | NULL    |       |
| party_diet_restriction_description | text             | YES  |     | NULL    |       |
| song_request_name                  | varchar(255)     | YES  |     | NULL    |       |
| song_request_artist                | varchar(255)     | YES  |     | NULL    |       |
| party_email                        | varchar(255)     | NO   |     | NULL    |       |
| party_plus_one                     | enum('y','n')    | YES  |     | NULL    |       |
| party_plus_one_name                | varchar(255)     | YES  |     | NULL    |       |
+------------------------------------+------------------+------+-----+---------+-------+
10 rows in set (0.00 sec)

mysql> desc attendees;
+-----------+--------------+------+-----+---------+----------------+
| Field     | Type         | Null | Key | Default | Extra          |
+-----------+--------------+------+-----+---------+----------------+
| id        | int unsigned | NO   | PRI | NULL    | auto_increment |
| party_id  | binary(16)   | YES  |     | NULL    |                |
| firstname | varchar(30)  | NO   |     | NULL    |                |
| lastname  | varchar(30)  | NO   |     | NULL    |                |
| attending | tinyint(1)   | NO   |     | 0       |                |
+-----------+--------------+------+-----+---------+----------------+
5 rows in set (0.00 sec)

mysql> desc bish_tracker;
+------------+--------------+------+-----+-------------------+-------------------+
| Field      | Type         | Null | Key | Default           | Extra             |
+------------+--------------+------+-----+-------------------+-------------------+
| id         | int unsigned | NO   | PRI | NULL              | auto_increment    |
| party_id   | binary(16)   | YES  |     | NULL              |                   |
| party_name | varchar(255) | YES  |     | NULL              |                   |
| ip         | varchar(255) | NO   |     | NULL              |                   |
| path       | varchar(125) | NO   |     | NULL              |                   |
| tstamp     | timestamp    | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
+------------+--------------+------+-----+-------------------+-------------------+
6 rows in set (0.00 sec)
