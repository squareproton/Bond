/** resolver
{
    "depends": [
        "common"
    ],
    "searchPath": "common"
}
*/

/** 
 * custom aggregate function for a integer set.
 * Don't forget to do some ordering before use
 *
 */
CREATE TYPE "int_set_fragment" as (
    "low" integer,
    "high" integer
);

CREATE TYPE "int_set_state" as ( 
    "complete" text[],
    "working" int_set_fragment
);

CREATE FUNCTION cast_int_set_fragment_as_text( int_set_fragment ) RETURNS text AS $$
BEGIN
    RETURN CASE 
        WHEN $1.low = $1.high THEN 
            $1.low::text
        ELSE 
            $1.low::text || '-' || $1.high::text
    END;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

CREATE CAST ( int_set_fragment AS text )
WITH FUNCTION cast_int_set_fragment_as_text( int_set_fragment ) AS ASSIGNMENT;

CREATE FUNCTION cast_int_set_state_as_text( int_set_state ) RETURNS text AS $$
BEGIN
    RETURN CASE WHEN $1.working IS NULL THEN
        array_to_string( $1.complete, ',' )
    ELSE
        array_to_string( $1.complete || $1.working::text, ',' )
    END;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

CREATE CAST ( int_set_state AS text )
WITH FUNCTION cast_int_set_state_as_text( int_set_state ) AS ASSIGNMENT;

CREATE FUNCTION int_set_accum( int_set_state, integer ) RETURNS int_set_state AS $$
BEGIN

    IF ($1).working.high + 1 = $2 THEN
        $1.working := ROW( ($1).working.low, $2 )::int_set_fragment;
    ELSE
        IF $1.working IS NULL THEN
            $1.working := ROW( $2, $2 )::int_set_fragment;
        ELSE 
            $1.complete := $1.complete || $1.working::text;
            $1.working := ROW( $2, $2 )::int_set_fragment;
        END IF;
    END IF;   

    RETURN $1;
    
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

-- select ROW( ARRAY[]::int_set_fragment[], ROW(1,2)::int_set_fragment )::int_set_state;

CREATE AGGREGATE int_set ( integer ) (
    SFUNC = int_set_accum,
    STYPE = int_set_state,
    FINALFUNC = cast_int_set_state_as_text,
    INITCOND = '({},)'
);