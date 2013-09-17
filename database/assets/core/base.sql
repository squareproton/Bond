/** resolver
{
    "depends": [
    ]
}
*/

/**
 * Stuff we think really should be common to all that use Bond.
 * Wanna change this stuff? Feel free. The most overtly breaking problem is the intervalstyle.
 */

SET client_encoding = 'UTF8';
SET statement_timeout = 0;
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

-- anonymous code block to get around the failure to alter the 'current database'
-- ANSI standard interval style for compatability with Php. Think Bond\Entity\Types\Interval
DO language plpgsql $$
DECLARE
    db text := quote_ident( current_database() );
BEGIN
    EXECUTE 'ALTER DATABASE ' || db || ' SET intervalstyle = iso_8601;';
END;
$$;
-- Undo with. SET intervalstyle = 'intervalstyle'

-- not required for running of the bond - this is just to prevent pgadmin throwing a warning every connect
CREATE EXTENSION IF NOT EXISTS adminpack WITH SCHEMA "pg_catalog";

-- assume we've got a public search path schema
SET search_path TO public;