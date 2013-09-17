/** resolver
{
    "depends": [
        "unit",
        "unit.a2"
    ],
    "searchPath": "unit"
}
*/


CREATE TABLE a1
(
    id serial NOT NULL,
    int4 integer,
    string text,
    cc character(2),
    b bool NOT NULL DEFAULT true,
    create_timestamp timestamp without time zone DEFAULT now(),
    foreign_key integer,
    CONSTRAINT a1_pkey PRIMARY KEY (id),
    CONSTRAINT a1_foreign_key_fkey FOREIGN KEY (foreign_key) REFERENCES a2 (id) MATCH SIMPLE ON UPDATE NO ACTION ON DELETE NO ACTION
);

COMMENT ON TABLE a1 IS E'
@normality.persist.links[]: A1linkA4
@normality.persist.references[]: A11.a1_id
%normality.instancesMaxAllowed: NULL
@api.findByKey: id
';

COMMENT ON COLUMN a1.string IS E'
comment-string
@normality.form-choicetext
';
