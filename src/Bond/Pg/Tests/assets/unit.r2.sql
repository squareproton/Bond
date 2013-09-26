/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE r2
(
    id serial NOT NULL,
    range tsrange,
    CONSTRAINT r2_pkey PRIMARY KEY (id)
);