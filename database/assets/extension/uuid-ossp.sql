/** resolver
{
    "depends": [
        "extension"
    ]
}
*/

CREATE EXTENSION uuid-ossp WITH SCHEMA extension;

SELECT build.search_path_add_schema('extension', 0, true);