/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

CREATE TYPE "enum_stream_direction" AS ENUM (
    'client_to_server',
    'server_to_client'
);

CREATE CAST (text AS enum_stream_direction) WITH INOUT AS IMPLICIT;
-- CREATE CAST (enum_stream_direction AS text) WITH INOUT AS IMPLICIT;