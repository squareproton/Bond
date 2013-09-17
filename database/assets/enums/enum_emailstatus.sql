/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

CREATE TYPE "enum_emailstatus" AS ENUM (
    'queued',
    'processing',
    'sent'
);

CREATE CAST (text AS enum_emailstatus) WITH INOUT AS IMPLICIT;
-- CREATE CAST (enum_emailstatus AS text) WITH INOUT AS IMPLICIT;