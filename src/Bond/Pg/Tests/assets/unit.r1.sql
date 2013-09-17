/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

-- Recursive linking entity.
-- Designed to stress the entity/recordmanager subsystem
CREATE TABLE r1
(
  id serial NOT NULL,
  "name" text,
  links integer,
  CONSTRAINT r1_pkey PRIMARY KEY (id),
  CONSTRAINT "recursive_linking_nice" FOREIGN KEY (links)
      REFERENCES r1 (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);
CREATE INDEX "fki_recursive_table_linking. Wow. Postgres rocks." ON r1 USING btree (links);