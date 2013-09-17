/** resolver
{
    "depends": [
        "unit",
        "unit.enumtype"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE resolver (
    "id" serial,
    "require_has_default" text NOT NULL DEFAULT 'spanner',
    "require_no_default" text NOT NULL,
    "optional" text NULL,
    "enum" enumtype NULL
);