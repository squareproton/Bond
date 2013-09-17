/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

CREATE TABLE "log"
(
  "logId" serial,
  op text not null,
  CONSTRAINT log_pk PRIMARY KEY ("logId")
);
COMMENT ON TABLE "log" IS E'
@normality.isLogTable: true
';