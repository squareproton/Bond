/** resolver
{
    "depends": [
        "logs"
    ],
    "searchPath": "logs"
}
*/

CREATE TYPE "enum_op_type" AS ENUM (
    'INSERT',
    'UPDATE',
    'DELETE',
    'TICK'
);

CREATE CAST (text AS enum_op_type) WITH INOUT AS IMPLICIT;
-- CREATE CAST (enum_op_type AS text) WITH INOUT AS IMPLICIT;