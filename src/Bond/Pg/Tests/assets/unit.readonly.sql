/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE "readonly"
(
    id serial,
    "name" text,
    CONSTRAINT readonly_pkey PRIMARY KEY (id)
);

COMMENT ON TABLE "readonly" IS E'
normality.isReadOnly: READONLY_EXCEPTION
';