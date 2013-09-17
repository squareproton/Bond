/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

-- bytea checker
CREATE TABLE "bytea"
(
  id serial NOT NULL,
  "bytea" bytea,
  CONSTRAINT bytea_pkey PRIMARY KEY (id)
);