/** resolver
{
    "depends": [
        "citext",
        "helper",
        "logs",
        "seq_logs"
    ],
    "searchPath": "logs, common, extension"
}
*/

CREATE TABLE "Tick" (
    "logId" integer NOT NULL DEFAULT nextval('"seq_LogId"'::regclass),
    "tick" bigint NOT NULL DEFAULT nextval('"seq_Tick"'::regclass),
    "name" citext,
    "createTimestamp" timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT "pk_Tick" PRIMARY KEY ("logId")
);

CREATE INDEX "idx_Tick_tick" on "Tick" USING btree ("tick");
CREATE INDEX "idx_Tick_name" on "Tick" USING btree ("name");

CREATE TRIGGER "trg_Tick_restrict"
    BEFORE UPDATE OR DELETE
    ON "Tick" FOR EACH STATEMENT EXECUTE PROCEDURE restrictTrigger();

-- Need to make this table readonly
COMMENT ON TABLE "Tick" IS '
@normality.isLogTable: true
';

CREATE FUNCTION "tick" ( in text ) RETURNS bigint
AS $$
	INSERT INTO "Tick" ( "name" ) VALUES( $1 ) RETURNING tick;
$$ LANGUAGE SQL;