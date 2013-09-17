/** resolver
{
    "depends": [
        "extension"
    ]
}
*/

CREATE EXTENSION pgcrypto WITH SCHEMA extension;

SELECT build.search_path_add_schema('extension', 0, true);