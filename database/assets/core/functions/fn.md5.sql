/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

/**
 * Return the md5 hash of a oid (pg_large_object) in the system
 */
CREATE OR REPLACE FUNCTION md5( id oid ) RETURNS text AS
$$
DECLARE
    fd integer;
    size integer;
    hashval text;
    INV_READ constant integer := 262144; -- 0x40000 from libpq-fs.h
    SEEK_SET constant integer := 0;
    SEEK_END constant integer := 2;
BEGIN
    IF id is null THEN
       RETURN NULL;
    END IF;
    fd   := lo_open(id, INV_READ);
    size := lo_lseek(fd, 0, SEEK_END);
    PERFORM lo_lseek(fd, 0, SEEK_SET);
    hashval := md5(loread(fd, size));
    PERFORM lo_close(fd);
    RETURN hashval;
END;
$$
LANGUAGE plpgsql STABLE STRICT;
COMMENT ON FUNCTION md5(id oid) is 'Return the md5 hash of a oid (pg_large_object) in the system.';

SELECT build.search_path_add_schema('common', 0, true);