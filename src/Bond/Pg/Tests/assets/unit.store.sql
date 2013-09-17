/** resolver
{
    "depends": [
        "unit",
        "hstore"
    ],
    "searchPath": "unit, extension"
}
*/

-- hstore entity checker
CREATE TABLE "store"
(
  id text NOT NULL,
  "store" hstore,
  CONSTRAINT hstore_pkey PRIMARY KEY (id)
);