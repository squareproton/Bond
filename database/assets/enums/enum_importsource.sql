/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

CREATE TYPE "enum_importsource" AS ENUM (
    'API',
    'CSV',
    'Manual'
);

CREATE CAST (text AS enum_importsource) WITH INOUT AS IMPLICIT;
-- CREATE CAST (enum_importsource AS text) WITH INOUT AS IMPLICIT;