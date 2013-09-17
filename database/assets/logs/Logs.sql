/** resolver
{
    "depends": [
        "enum_op_type",
        "logs",
        "seq_logs"
    ],
    "searchPath": "logs"
}
*/

CREATE TABLE "Logs" (
    "logId" bigint NOT NULL DEFAULT nextval('"seq_LogId"'::regclass),
    "tick" bigint NOT NULL DEFAULT currval('"seq_Tick"'::regclass),
    "op" "enum_op_type" NOT NULL,
    CONSTRAINT "pk_Logs" PRIMARY KEY ("logId")
);

CREATE INDEX "idx_Logs_tick" on "Logs" USING btree ("tick");

COMMENT ON TABLE "Logs" IS '
@normality.isLogTable: true
';
