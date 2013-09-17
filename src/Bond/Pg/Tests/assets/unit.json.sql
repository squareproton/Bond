/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

-- json entity checker
CREATE TABLE "json"
(
  id text NOT NULL,
  "json" json,
  CONSTRAINT json_pkey PRIMARY KEY (id)
);