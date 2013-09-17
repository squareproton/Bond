/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

-- inet entity checker
CREATE TABLE "inet"
(
  id serial NOT NULL,
  "ip" inet,
  CONSTRAINT inet_pkey PRIMARY KEY (id)
);