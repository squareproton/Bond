/** resolver
{
    "depends": [
        "base",
        "fn.md5"
    ]
}
*/

CREATE SCHEMA unit;
COMMENT ON SCHEMA unit IS 'Main unit testing schema for Bond. Not required for running of any app.';

SELECT build.search_path_add_schema('unit', 0, true);