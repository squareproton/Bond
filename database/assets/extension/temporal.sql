/** resolver
{
    "depends": [
        "extension"
    ]
}
*/

-- temporal has a hardcoded dependany on the public schema.
-- you'll need to modify /use/share/postgresql/9.1/extension/temporal--0.7.1.sql to change the schema it uses
-- recomend just commenting out the line "SET search_path = ..."

-- This is currently bugged. Skipping.
-- CREATE EXTENSION temporal WITH SCHEMA extension;
SELECT 1;