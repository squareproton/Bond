/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE f1 (
    id serial NOT NULL,
    "name" text,
    "oid" oid,
    CONSTRAINT f1_pkey PRIMARY KEY (id)
);