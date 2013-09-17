/** resolver
{
    "depends": [
        "unit",
        "unit.enumtype"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE a4 (
    id serial NOT NULL,
    "name" text,
    "type" enumtype NOT NULL,
    "typeNullable" enumtype NULL DEFAULT NULL,
    CONSTRAINT "a4_pkey" PRIMARY KEY ("id")
);

COMMENT ON TABLE a4 IS E'@normality.save.links[]: a1linka4\n';
COMMENT ON COLUMN a4.type IS E'%form.preferred_options: ["one"]\n';