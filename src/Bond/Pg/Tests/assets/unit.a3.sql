/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

-- dual column primary keys
CREATE TABLE a3
(
  pk1 integer NOT NULL,
  pk2 integer NOT NULL,
  CONSTRAINT a3_pkey PRIMARY KEY (pk1, pk2)
);
COMMENT ON TABLE a3 IS E'comment';