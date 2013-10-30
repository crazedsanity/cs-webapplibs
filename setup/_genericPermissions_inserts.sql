--
-- Data for Name: cswal_permission_table; Type: TABLE DATA; Schema: public; Owner: www
--

INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/blog/slaughter/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/blog/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/blog/prophet/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/member/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/member/ttorp/', 0);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/member/public/', 15);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/blog/help/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/blog/codeblog/', 2);
INSERT INTO cswal_permission_table (location, default_permissions) VALUES ('/member', 2);


--
-- Data for Name: cswal_group_table; Type: TABLE DATA; Schema: public; Owner: www
--

INSERT INTO cswal_group_table (group_name, group_description) VALUES ('members', 'Users can access the "members" area');
INSERT INTO cswal_group_table (group_name, group_description) VALUES ('admins', 'Administrators');
INSERT INTO cswal_group_table (group_name, group_description) VALUES ('codeblog_contributors', 'Contributors to "The Code Blog"');
INSERT INTO cswal_group_table (group_name, group_description) VALUES ('member_admin', 'Admins that can do things in the "members" area');
INSERT INTO cswal_group_table (group_name, group_description) VALUES ('ttorp_user', 'Users that can access TTORP');


INSERT INTO cswal_group_permission_table (group_id, permission_id, permissions) VALUES (2, 1, 15);
INSERT INTO cswal_group_permission_table (group_id, permission_id, permissions) VALUES (3, 9, 15);
INSERT INTO cswal_group_permission_table (group_id, permission_id, permissions) VALUES (5, 6, 7);
INSERT INTO cswal_group_permission_table (group_id, permission_id, permissions) VALUES (1, 5, 2);

INSERT INTO cswal_user_group_table(user_id, group_id) VALUES (101, 3);
INSERT INTO cswal_user_group_table(user_id, group_id) VALUES (102, 3);
INSERT INTO cswal_user_group_table(user_id, group_id) VALUES (116, 3);

INSERT INTO cswal_user_permission_table (user_id, permission_id, permissions) VALUES (101, 9, 15);
INSERT INTO cswal_user_permission_table (user_id, permission_id, permissions) VALUES (102, 8, 7);
INSERT INTO cswal_user_permission_table (user_id, permission_id, permissions) VALUES (101, 2, 15);
