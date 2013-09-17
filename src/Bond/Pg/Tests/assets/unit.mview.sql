/** resolver
{
    "depends": [
        "unit"
    ],
    "searchPath": "unit"
}
*/

-- materialized view
CREATE TABLE "mView"
(
  "name" text
);
COMMENT ON TABLE "mView" IS E'
@normality.isMaterialisedView: true
';