/** resolver
{
    "depends": [
        "logs"
    ],
    "searchPath": "logs"
}
*/

CREATE TYPE "enum_socketIOapp" AS ENUM (
    'uberdebug',
    'piggy',
    'dash',
    'scribe'
);

CREATE CAST (text AS "enum_socketIOapp") WITH INOUT AS IMPLICIT;
-- CREATE CAST ("enum_socketIOapp" AS text) WITH INOUT AS IMPLICIT;