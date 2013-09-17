/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

CREATE TYPE "enum_role" AS ENUM (
    'api',
    'web'
);

CREATE CAST (text AS enum_role) WITH INOUT AS IMPLICIT;
-- CREATE CAST (enum_role AS text) WITH INOUT AS IMPLICIT;