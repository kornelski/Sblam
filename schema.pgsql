--
-- Table structure for table "account_messages"
--

CREATE TYPE enum_n_y AS ENUM('N','Y');

DROP TABLE IF EXISTS "account_messages";
CREATE TABLE "account_messages" (
  "id" SERIAL NOT NULL,
  "account" INTEGER NOT NULL,
  "type" varchar(16) DEFAULT NULL,
  "message_html" TEXT NOT NULL,
  "read" enum_n_y NOT NULL DEFAULT 'N',
  "sent" INTEGER NOT NULL DEFAULT date_part('epoch',NOW()),
  PRIMARY KEY ("id"),
  UNIQUE ("account","type")
);

--
-- Table structure for table "accounts"
--

DROP TABLE IF EXISTS "accounts";
CREATE TABLE "accounts" (
  "id" SERIAL NOT NULL,
  "apikey" char(18) NOT NULL,
  "created" TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
  "email" TEXT,
  "apikeyhash" bytea /*16*/ NOT NULL DEFAULT '',
  "script" enum_n_y NOT NULL DEFAULT 'N',
  "lasthost" TEXT,
  PRIMARY KEY ("id"),
  UNIQUE ("apikey")
);

CREATE INDEX "account_apikeyhash" ON accounts(apikeyhash);

CREATE LANGUAGE plpgsql;
CREATE OR REPLACE FUNCTION account_hash() RETURNS trigger AS
$BODY$
    BEGIN
        NEW.apikeyhash := decode(md5( E'^&$@$2\n' || COALESCE(NEW.apikey,'') || '@@'),'hex');
        return NEW;
    END;
$BODY$
LANGUAGE plpgsql STABLE;

CREATE TRIGGER "account_hash" BEFORE INSERT ON accounts FOR EACH ROW EXECUTE PROCEDURE account_hash();

--
-- Table structure for table "bayestotal"
--

DROP TABLE IF EXISTS "bayestotal";
CREATE TABLE "bayestotal" (
  "totalspam" INTEGER NOT NULL DEFAULT 0,
  "totalham" INTEGER NOT NULL DEFAULT 0
);

--
-- Table structure for table "bayestranslate"
--

DROP TABLE IF EXISTS "bayestranslate";
CREATE TABLE "bayestranslate" (
  "wordh" bytea /*16*/ NOT NULL,
  "word" TEXT NOT NULL,
  PRIMARY KEY ("wordh")
);

--
-- Table structure for table "bayeswordsh"
--

DROP TABLE IF EXISTS "bayeswordsh";
CREATE TABLE "bayeswordsh" (
  "wordh" bytea /*16*/ NOT NULL,
  "ham" INTEGER NOT NULL DEFAULT 0,
  "spam" INTEGER NOT NULL DEFAULT 0,
  "added" INTEGER NOT NULL DEFAULT date_part('epoch',NOW()),
  "flags" "char" NOT NULL,
  PRIMARY KEY ("wordh")
);

--
-- Table structure for table "bayeswordsh_s"
--

DROP TABLE IF EXISTS "bayeswordsh_s";
CREATE TABLE "bayeswordsh_s" (
  "wordh" bytea /*16*/ NOT NULL,
  "ham" INTEGER NOT NULL DEFAULT 0,
  "spam" INTEGER NOT NULL DEFAULT 0,
  "added" INTEGER NOT NULL DEFAULT date_part('epoch',NOW()),
  PRIMARY KEY ("wordh")
);


CREATE OR REPLACE FUNCTION bayeswordsh_i() RETURNS trigger AS
$BODY$
    BEGIN
        UPDATE bayeswordsh_s SET spam=NEW.spam, ham=NEW.ham, added=NEW.added WHERE wordh = NEW.wordh;
        IF NOT FOUND THEN 
            BEGIN
                INSERT INTO bayeswordsh_s(wordh,spam,ham,added) VALUES (NEW.wordh,NEW.spam,NEW.ham,NEW.added);
            EXCEPTION WHEN unique_violation THEN
                -- do nothing
            END;    
        END IF;
    END;
$BODY$
LANGUAGE plpgsql STABLE;

CREATE TRIGGER "bayeswordsh_i" BEFORE INSERT ON bayeswordsh FOR EACH ROW EXECUTE PROCEDURE bayeswordsh_i();
CREATE TRIGGER "bayeswordsh_u" BEFORE UPDATE ON bayeswordsh FOR EACH ROW EXECUTE PROCEDURE bayeswordsh_i();

--
-- Table structure for table "dnscache"
--

DROP TABLE IF EXISTS "dnscache";
CREATE TABLE "dnscache" (
  "host" TEXT NOT NULL,
  "ip" INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY ("host","ip")
);

--
-- Table structure for table "dnsrevcache"
--

DROP TABLE IF EXISTS "dnsrevcache";
CREATE TABLE "dnsrevcache" (
  "ip" INTEGER NOT NULL DEFAULT 0,
  "host" TEXT NOT NULL,
  PRIMARY KEY ("ip")
);

CREATE INDEX "dnsrevcache_host" on dnsrevcache(host);

--
-- Table structure for table "dupes"
--

DROP TABLE IF EXISTS "dupes";
CREATE TABLE "dupes" (
  "checksum" bytea /*16*/ NOT NULL,
  "count" INTEGER NOT NULL DEFAULT 1,
  "ip" INTEGER NOT NULL,
  "expires" INTEGER NOT NULL,
  PRIMARY KEY ("checksum")
);

--
-- Table structure for table "feeds"
--

DROP TABLE IF EXISTS "feeds";
CREATE TABLE "feeds" (
  "id" SERIAL NOT NULL,
  "url" TEXT NOT NULL,
  "ignoreurls" TEXT,
  "killbylink" TEXT,
  "killbytext" TEXT,
  "prefilter" TEXT NOT NULL,
  "postfilter" TEXT NOT NULL,
  "ignoretitles" enum_n_y NOT NULL,
  "lastdate" INTEGER,
  "lastcheck" INTEGER,
  PRIMARY KEY ("id")
);

--
-- Table structure for table "linkstotal"
--

DROP TABLE IF EXISTS "linkstotal";
CREATE TABLE "linkstotal" (
  "totalspam" INTEGER NOT NULL DEFAULT 0,
  "totalham" INTEGER NOT NULL DEFAULT 0
);

--
-- Table structure for table "linkstranslate"
--

DROP TABLE IF EXISTS "linkstranslate";
CREATE TABLE "linkstranslate" (
  "wordh" bytea /*16*/ NOT NULL,
  "word" TEXT NOT NULL,
  PRIMARY KEY ("wordh")
);

--
-- Table structure for table "linkswordsh"
--

DROP TABLE IF EXISTS "linkswordsh";
CREATE TABLE "linkswordsh" (
  "wordh" bytea /*16*/ NOT NULL,
  "ham" INTEGER NOT NULL DEFAULT 0,
  "spam" INTEGER NOT NULL DEFAULT 0,
  "added" INTEGER NOT NULL DEFAULT date_part('epoch',NOW()),
  "flags" "char" NOT NULL,
  PRIMARY KEY ("wordh")
);

--
-- Table structure for table "linkswordsh_s"
--

DROP TABLE IF EXISTS "linkswordsh_s";
CREATE TABLE "linkswordsh_s" (
  "wordh" bytea /*16*/ NOT NULL,
  "ham" INTEGER NOT NULL DEFAULT 0,
  "spam" INTEGER NOT NULL DEFAULT 0,
  "added" INTEGER NOT NULL DEFAULT date_part('epoch',NOW()),
  PRIMARY KEY ("wordh")
);


CREATE OR REPLACE FUNCTION linkswordsh_i() RETURNS trigger AS
$BODY$
    BEGIN
        UPDATE linkswordsh_s SET spam=NEW.spam, ham=NEW.ham, added=NEW.added WHERE wordh = NEW.wordh;
        IF NOT FOUND THEN 
            BEGIN
                INSERT INTO linkswordsh_s(wordh,spam,ham,added) VALUES (NEW.wordh,NEW.spam,NEW.ham,NEW.added);
            EXCEPTION WHEN unique_violation THEN
                -- do nothing
            END;    
        END IF;
    END;
$BODY$
LANGUAGE plpgsql STABLE;

CREATE TRIGGER "linkswordsh_i" BEFORE INSERT ON linkswordsh FOR EACH ROW EXECUTE PROCEDURE linkswordsh_i();
CREATE TRIGGER "linkswordsh_u" BEFORE UPDATE ON linkswordsh FOR EACH ROW EXECUTE PROCEDURE linkswordsh_i();

--
-- Table structure for table "plonker"
--

DROP TABLE IF EXISTS "plonker";
CREATE TABLE "plonker" (
  "ip" INTEGER NOT NULL,
  "spampoints" INTEGER NOT NULL,
  "added" INTEGER NOT NULL DEFAULT date_part('epoch',NOW()),
  "flags" INTEGER NOT NULL, /* set('dul','nodul','wild','nowild') */
  PRIMARY KEY ("ip")
);


--
-- Table structure for table "posts_meta"
--

DROP TABLE IF EXISTS "posts_meta";
CREATE TABLE "posts_meta" (
  "id" SERIAL NOT NULL,
  "account" INTEGER,
  "ip" INTEGER NOT NULL DEFAULT 0,
  "timestamp" INTEGER,
  "spambayes" SMALLINT,
  "spamscore" INTEGER,
  "spamcert" INTEGER,
  "worktime" INTEGER,
  "added" SMALLINT,
  "manualspam" SMALLINT,
  "serverid" varchar(64) NOT NULL,
  PRIMARY KEY ("id")
);

CREATE INDEX "posts_meta_manualspam" ON "posts_meta" ("manualspam","spamscore");
CREATE INDEX "posts_meta_account" ON "posts_meta" ("account");
CREATE INDEX "posts_meta_spamscore" ON "posts_meta" ("spamscore","spamcert");

--
-- Table structure for table "posts_data"
--

DROP TABLE IF EXISTS "posts_data";
CREATE TABLE "posts_data" (
  "id" INTEGER NOT NULL REFERENCES posts_data(id),
  "content" text NOT NULL,
  "name" TEXT,
  "email" TEXT,
  "url" TEXT,
  "headers" TEXT,
  "cookies" BOOLEAN NOT NULL DEFAULT false,
  "session" BOOLEAN NOT NULL DEFAULT false,
  "host" TEXT,
  "hostip" INTEGER NOT NULL DEFAULT 0,
  "path" TEXT,
  "post" TEXT,
  "chcookie" TEXT,
  "spamreason" TEXT,
  "profiling" TEXT,
  UNIQUE ("id")
);

--
-- Table structure for table "postsarchive"
--

DROP TABLE IF EXISTS "postsarchive";
CREATE TABLE "postsarchive" (
  "id" INTEGER NOT NULL,
  "spambayes" SMALLINT,
  "spamscore" INTEGER,
  "spamcert" INTEGER,
  "spamreason" TEXT,
  "manualspam" SMALLINT,
  "content" TEXT NOT NULL,
  "name" TEXT,
  "email" TEXT,
  "url" TEXT,
  "ip" INTEGER NOT NULL,
  "timestamp" INTEGER,
  "headers" TEXT,
  "cookies" BOOLEAN NOT NULL DEFAULT false,
  "session" BOOLEAN NOT NULL DEFAULT false,
  "host" TEXT,
  "hostip" INTEGER NOT NULL,
  "path" TEXT,
  "submitname" TEXT,
  "added" SMALLINT,
  "checksum" varchar(56) DEFAULT NULL,
  "post" TEXT,
  "chcookie" TEXT,
  "worktime" INTEGER,
  "account" INTEGER,
  "profiling" TEXT
);

--
-- Table structure for table "trustedproxies"
--

DROP TABLE IF EXISTS "trustedproxies";
CREATE TABLE "trustedproxies" (
  "host" TEXT NOT NULL,
  PRIMARY KEY ("host")
);


--- shims

CREATE OR REPLACE FUNCTION from_unixtime(bigint)
RETURNS timestamp without time zone AS $$
  SELECT pg_catalog.to_timestamp($1)::timestamp without time zone
$$ IMMUTABLE STRICT LANGUAGE SQL;


CREATE OR REPLACE FUNCTION timestampdiff(text, timestamp without time zone, timestamp without time zone)
RETURNS integer AS $$
  SELECT CEIL(EXTRACT(epoch FROM ($3 - $2)) / EXTRACT(epoch FROM ('1 ' operator(pg_catalog.||) $1)::interval))::integer
$$ IMMUTABLE STRICT LANGUAGE SQL;

CREATE OR REPLACE FUNCTION timestampdiff(text, timestamp without time zone, timestamp with time zone)
RETURNS integer AS $$
  SELECT timestampdiff($1, $2, $3::timestamp without time zone)
$$ IMMUTABLE STRICT LANGUAGE SQL;

