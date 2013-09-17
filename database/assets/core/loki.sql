/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

/**
 * Submit a value to loki
 */
CREATE OR REPLACE FUNCTION loki( accountId int, message text, payload json ) RETURNS void AS $$
DECLARE
BEGIN
    PERFORM pg_notify(
        'loki',
        '["' || accountId || '.' || replace(message, '"', '\"') || '", ' || payload::text || ']'
    );
END
$$ LANGUAGE plpgsql VOLATILE STRICT;