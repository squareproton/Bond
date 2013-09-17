/** resolver
{
    "depends": [
        "extension"
    ]
}
*/

CREATE EXTENSION citext WITH SCHEMA extension;

SELECT build.search_path_add_schema('extension', 0, true);