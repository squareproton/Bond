/** resolver
{
    "depends": []
}
*/

DO language plpgsql $$
BEGIN
    -- plv8 installed?
    IF NOT EXISTS(
        SELECT * FROM pg_available_extensions WHERE installed_version IS NOT NULL AND name = 'plv8'
    ) THEN
        -- this always gets installed in the pg_catalog
        CREATE EXTENSION plv8;
    END IF;
END;
$$;