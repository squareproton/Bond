/** resolver
{
    "depends": [
        "unit",
        "unit.a4"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE a1_link_a4 (
    a1_id int4 NOT NULL,
    a4_id int4 NOT NULL,
    a1_idranking int4,
    a4_idranking int4,
    CONSTRAINT a1_link_a4_pkey PRIMARY KEY (a1_id, a4_id),
    CONSTRAINT a4_ref FOREIGN KEY (a4_id) REFERENCES a4 (id) ON DELETE CASCADE ON UPDATE NO ACTION
);

COMMENT ON TABLE a1_link_a4 IS E'
@normality.references[]: a1_id=a1.id
@normality.entity: Link
@normality.entity-name: A1linkA4
';