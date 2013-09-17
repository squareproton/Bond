/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

CREATE TYPE "enum_connectionstate" AS ENUM (
    'connected',
    'disconnected'
);

CREATE CAST (text AS enum_connectionstate) WITH INOUT AS IMPLICIT;
-- CREATE CAST (enum_connectionstate AS text) WITH INOUT AS IMPLICIT;