/** resolver
{
    "depends": [
        "unit",
        "unit.a1",
        "unit.a2"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE a1_child
(
  col1 text,
  col2 text,
  CONSTRAINT pk_a1_child PRIMARY KEY (id),
  CONSTRAINT a1_child_foreign_key_fkey FOREIGN KEY (foreign_key) REFERENCES a2 (id) MATCH SIMPLE ON UPDATE NO ACTION ON DELETE NO ACTION
) INHERITS ( a1 );
