


--
-- Name: cs_authentication_table; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE cs_authentication_table (
    uid integer NOT NULL,
    username text NOT NULL,
    passwd character varying(32),
    is_active boolean DEFAULT true NOT NULL,
    date_created date DEFAULT now() NOT NULL,
    last_login timestamp with time zone,
    email text,
    user_status_id integer
);


--
-- Name: cs_authentication_table_uid_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE cs_authentication_table_uid_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


--
-- Name: cs_authentication_table_uid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE cs_authentication_table_uid_seq OWNED BY cs_authentication_table.uid;


--
-- Name: cs_authentication_table_uid_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('cs_authentication_table_uid_seq', 121, true);


--
-- Name: uid; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE cs_authentication_table ALTER COLUMN uid SET DEFAULT nextval('cs_authentication_table_uid_seq'::regclass);


--
-- Data for Name: cs_authentication_table; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO cs_authentication_table VALUES 
	(101, 'slaughter', 'x', true, '2008-06-01', '2011-01-10 21:07:07.029629-06', 'slaughter@dev.null', 1),
	(102, 'mary', 'x', true, '2008-06-01', '2011-01-10 21:07:07.029629-06', 'mary@dev.null', 1),
	(103, 'einstein', 'x', true, '2008-06-01', '2011-01-10 21:07:07.029629-06', 'einstein@dev.null', 1),
	(104, 'alexander.graham.bell', 'x', true, '2008-06-01', '2011-01-10 21:07:07.029629-06', 'alexander.graham.bell@dev.null', 1),
	(105, 'john', 'x', true, '2008-06-01', '2011-01-10 21:07:07.029629-06', 'john@dev.null', 1),
	(106, 'xavier', 'x', true, '2008-06-01', '2011-01-10 21:07:07.029629-06', 'xavier@dev.null', 1);


--
-- Name: cs_authentication_table_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY cs_authentication_table
    ADD CONSTRAINT cs_authentication_table_pkey PRIMARY KEY (uid);


--
-- Name: cs_authentication_table_username_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY cs_authentication_table
    ADD CONSTRAINT cs_authentication_table_username_key UNIQUE (username);


--
-- PostgreSQL database dump complete
--

