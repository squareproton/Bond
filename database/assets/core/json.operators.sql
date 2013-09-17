/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

CREATE OR REPLACE FUNCTION json_cmp(
    json,
    json
) RETURNS INTEGER LANGUAGE SQL STRICT IMMUTABLE AS $$
    SELECT bttextcmp($1::text, $2::text);
$$;

CREATE OR REPLACE FUNCTION json_eq(
    json,
    json
) RETURNS BOOLEAN LANGUAGE SQL STRICT IMMUTABLE AS $$
    SELECT bttextcmp($1::text, $2::text) = 0;
$$;

CREATE OPERATOR = (
    LEFTARG   = json,
    RIGHTARG  = json,
    PROCEDURE = json_eq
);

CREATE OPERATOR CLASS json_ops
DEFAULT FOR TYPE JSON USING btree AS
OPERATOR    3   =  (json, json),
FUNCTION    1   json_cmp(json, json);