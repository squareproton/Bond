/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

/**
 * Misc utility functions for the bond ecosystem
 *
 * Specialised functions that can be logically grouped together should go in their own file
 */

-- Pete. Not quite sure what these two lines are doing. Temporarily commented out. You might need to put them back in.
-- CREATE OR REPLACE FUNCTION plpgsql_call_handler() RETURNS language_handler AS '$libdir/plpgsql' LANGUAGE C;
-- CREATE TRUSTED LANGUAGE plpgsql HANDLER "plpgsql_call_handler";

/**
 * Generate a salt
 */
CREATE OR REPLACE FUNCTION salt_generate() RETURNS text AS
$$
BEGIN
	RETURN md5(
		current_database() ||
		user ||
		current_timestamp ||
		random()
	);
END;
$$
LANGUAGE plpgsql VOLATILE;
COMMENT ON FUNCTION salt_generate() is 'Generate a random salt.';

/**
 * Generate the 'bond' (the password that is stored in the db) from the user entered password
 */
CREATE OR REPLACE FUNCTION bond_password( password text, salt text ) RETURNS text AS
$$
DECLARE result text;
BEGIN
	result := password || salt;
	FOR i IN 1..1000 LOOP
		result := md5(result || salt);
	END LOOP;
    RETURN result;
END;
$$
LANGUAGE plpgsql STRICT IMMUTABLE;
COMMENT ON FUNCTION bond_password( password text, salt text ) is 'Generate the ''bond'' (the password that is stored in the db) from the user entered password.';

/**
 * Trigger a restrictTrigger
 */
CREATE FUNCTION restrictTrigger() RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
BEGIN

    RAISE EXCEPTION E'Operation % is restricted on table %s',
        TG_OP,
        TG_RELNAME;

    RETURN NULL;

END;$$;

/**
 * Show a human readable representation of a time interval
 */
DROP FUNCTION IF EXISTS "interval_human"( interval );
CREATE FUNCTION interval_human( interval ) RETURNS text AS
$$
DECLARE
    i interval;
BEGIN
    i := greatest( $1, -1 * $1 );
RETURN CASE
        WHEN i < INTERVAL '1 minute' THEN 'Just now'
        WHEN i < INTERVAL '5 minute' THEN '5 minutes ago'
        WHEN i < INTERVAL '15 minute' THEN '15 minutes ago'
        WHEN i < INTERVAL '30 minute' THEN '30 minutes ago'
        WHEN i < INTERVAL '1 hour' THEN '1 hour ago'
        WHEN i < INTERVAL '2 hour' THEN '2 hours ago'
        WHEN i < INTERVAL '3 hour' THEN '3 hours ago'
        WHEN i < INTERVAL '4 hour' THEN '4 hours ago'
        WHEN date_trunc( 'day', NOW()::timestamp - i ) = date_trunc( 'day', NOW()::timestamp ) THEN 'today'
        WHEN date_trunc( 'day', NOW()::timestamp - i ) = date_trunc( 'day', NOW()::timestamp - '1 day'::interval ) THEN 'yesterday'
        WHEN i < INTERVAL '2 day' THEN '2 days ago'
        WHEN i > INTERVAL '2 day' AND i <= INTERVAL '6 day' THEN ROUND( EXTRACT( EPOCH FROM i ) / 86400 ) || ' days ago'
        WHEN i < INTERVAL '7 day' THEN 'A week ago'
        WHEN i < INTERVAL '1 month' THEN 'A month ago'
        WHEN i < INTERVAL '1 year' THEN 'A year ago'
        ELSE 'A long time ago...'
    END;
END;
$$
LANGUAGE plpgsql IMMUTABLE;

COMMENT ON FUNCTION interval_human(interval) is 'A human readable representation of a interval';

/**
 * Make a interval postive valued only
 */
CREATE OR REPLACE FUNCTION abs( i interval ) RETURNS interval AS $$
DECLARE
BEGIN
    RETURN CASE
        WHEN i < 'PT0S' THEN -1 * i
        ELSE i
    END;
END
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

-- disable a table's triggers
-- see, http://archives.postgresql.org/pgsql-general/2008-08/msg00923.php
CREATE OR REPLACE FUNCTION trigger_enable( enable boolean) RETURNS integer AS
$$
DECLARE
    mytables RECORD;
BEGIN
    FOR mytables IN SELECT relname FROM pg_class WHERE reltriggers > 0 AND NOT relname LIKE 'pg_%'
    LOOP
        IF enable THEN
            EXECUTE 'ALTER TABLE ' || quote_ident( mytables.relname ) || ' ENABLE TRIGGER ALL';
        ELSE
            EXECUTE 'ALTER TABLE ' || quote_ident( mytables.relname ) || ' DISABLE TRIGGER ALL';
        END IF;
    END LOOP;
    RETURN 1;
END;
$$
LANGUAGE 'plpgsql' VOLATILE;