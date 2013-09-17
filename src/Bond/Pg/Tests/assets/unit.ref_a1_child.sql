/** resolver
{
    "depends": [
        "unit",
        "unit.a1_child"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE ref_a1_child
(
  id serial NOT NULL,
  a1_child_id integer,
  CONSTRAINT fk_ref_a1_child FOREIGN KEY (a1_child_id) REFERENCES a1_child(id) MATCH SIMPLE ON UPDATE NO ACTION ON DELETE NO ACTION
);