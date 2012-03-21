BEGIN;

-- 
-- chat categories (ways to insulate chat rooms)
--
CREATE TABLE cswal_chat_category_table (
	chat_category_id serial NOT NULL PRIMARY KEY,
	category_name text NOT NULL
);

INSERT INTO cswal_chat_category_table (chat_category_id, category_name) VALUES (0, 'DEFAULT');

-- 
-- Chat rooms
-- 
CREATE TABLE cswal_chat_room_table (
	chat_room_id serial NOT NULL PRIMARY KEY,
	chat_category_id integer NOT NULL REFERENCES cswal_chat_category_table(chat_category_id) DEFAULT 0,
	room_name text NOT NULL,
	room_description text,
	creation timestamptz NOT NULL DEFAULT NOW(),
	is_private boolean NOT NULL DEFAULT false,
	is_closed boolean NOT NULL DEFAULT false,
	encoding text
);


-- 
-- Chat messages
-- NOTE::: change the reference on "uid" and "private_message_uid" to match your database schema.
-- NOTE::: the "private_message_uid" field is for sending private messages (intended for a specific user).
-- 
CREATE TABLE cswal_chat_message_table (
	chat_message_id serial NOT NULL PRIMARY KEY,
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	private_message_uid integer DEFAULT NULL REFERENCES cs_authentication_table(uid),
	chat_room_id integer NOT NULL REFERENCES cswal_chat_room_table(chat_room_id),
	creation timestamptz NOT NULL DEFAULT NOW(),
	message text NOT NULL
);


-- 
-- Participant table
-- NOTE: this is a *transient* table; it only has data when the chat room is active.
-- 
CREATE TABLE cswal_chat_participant_table (
	chat_participant_id serial NOT NULL PRIMARY KEY,
	chat_room_id integer NOT NULL REFERENCES cswal_chat_room_table(chat_room_id),
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	enter_timestamp timestamptz NOT NULL DEFAULT NOW(),
	last_received_message_id integer REFERENCES cswal_chat_message_table(chat_message_id)
);

