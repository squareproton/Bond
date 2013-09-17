/** resolver
{
    "depends": [
        "base"
    ]
}
*/

CREATE SCHEMA logs;
COMMENT ON SCHEMA logs IS 'Autologging tables are stored in this schema. Superfically this will look similar to app.';
