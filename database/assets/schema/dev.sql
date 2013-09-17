/** resolver
{
    "depends": [
        "base"
    ]
}
*/

CREATE SCHEMA dev;

COMMENT ON SCHEMA dev IS E'
Development functions and views. Contains some views for code automatic code generation.
This won''t be avaliable in production and shouldn''t be relied upon for general system running
';
