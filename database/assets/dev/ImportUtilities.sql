/** resolver
{
    "depends": [
        "dev"
    ],
    "searchPath": "dev"
}
*/

-- produce a random int in a range
DROP FUNCTION IF EXISTS rand_int(l int, h int);
CREATE FUNCTION rand_int(l int, h int) RETURNS int AS
$$
    SELECT FLOOR((RANDOM() * (1 + $2 - $1) + $1))::int;
$$ LANGUAGE SQL;

-- get a random integer from a array
DROP FUNCTION IF EXISTS rand_array( a int[] );
CREATE FUNCTION rand_array( a int[] ) RETURNS int AS
$$
BEGIN
    RETURN ($1)[ ceil( random() * array_length($1,1) )];
END
$$ LANGUAGE plpgsql VOLATILE STRICT;

-- get a random enum value
DROP FUNCTION IF EXISTS rand_enum(name text, n INT);
CREATE FUNCTION rand_enum(name text, n INT) RETURNS SETOF text AS
$$
    WITH _enum AS (
        SELECT
            MAX(enumsortorder)::int AS max,
            pg_enum.enumtypid
        FROM
            pg_type,
            pg_enum
        WHERE
            pg_type.oid = pg_enum.enumtypid
        AND
            pg_type.typname = $1
        GROUP BY
            pg_enum.enumtypid
    ),
    _maxenum AS (
        SELECT
            i,
            dev.rand_int(1, _enum.max) AS rand_index,
            _enum.enumtypid
        FROM
            generate_series(1, $2) AS g(i),
            _enum
    )
    SELECT
        enumlabel::text AS enum_value
    FROM
        pg_enum
    INNER JOIN
        _maxenum
    ON
        (_maxenum.rand_index = pg_enum.enumsortorder AND _maxenum.enumtypid = pg_enum.enumtypid);
$$ LANGUAGE SQL;