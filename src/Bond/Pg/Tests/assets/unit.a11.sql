/** resolver
{
    "depends": [
        "unit",
        "unit.a1"
    ],
    "searchPath": "unit"
}
*/

-- table with derived primary key
CREATE TABLE a11
(
  a1_id integer NOT NULL,
  name text,
  CONSTRAINT a11_pk PRIMARY KEY (a1_id)
);
-- manually defined references
COMMENT ON TABLE a11 IS E'@normality.references[]: a1_id=a1.id\n';