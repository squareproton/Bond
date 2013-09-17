/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE a2
(
  id serial NOT NULL,
  "name" text,
  CONSTRAINT a2_pkey PRIMARY KEY (id)
);

COMMENT ON TABLE a2 IS E'@normality.persist.references[]: A1.foreign_key\n';