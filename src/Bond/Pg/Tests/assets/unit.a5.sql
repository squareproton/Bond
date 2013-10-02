/** resolver
{
    "depends": [
        "unit",
        "unit.a4"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE a5 (
    id serial NOT NULL,
    "a4_id_one" int NOT NULL,
    "a4_id_two" int NOT NULL,
    CONSTRAINT "a5_pkey" PRIMARY KEY ("id"),
    CONSTRAINT a4_one_ref FOREIGN KEY (a4_id_one) REFERENCES a4 (id) ON DELETE CASCADE ON UPDATE NO ACTION,
    CONSTRAINT a4_two_ref FOREIGN KEY (a4_id_two) REFERENCES a4 (id) ON DELETE CASCADE ON UPDATE NO ACTION
);

-- $this->normalityTags['alias']['reference']
COMMENT ON TABLE a5 IS E'%normality.alias.reference: "spanner"\n';
-- COMMENT ON COLUMN a4.type IS E'%form.preferred_options: ["one"]\n';