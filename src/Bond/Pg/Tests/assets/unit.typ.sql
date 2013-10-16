/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE typ
(
  id serial PRIMARY KEY,
  i integer,
  ta text[],
  ia int[],
  s text,
  c character(2),
  b boolean NOT NULL DEFAULT true,
  t timestamp without time zone DEFAULT now()
);