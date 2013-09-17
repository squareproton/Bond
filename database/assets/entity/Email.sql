/** resolver
{
    "depends": [
        "enum_emailstatus",
        "common"
    ],
    "searchPath": "common, extension"
}
*/

CREATE SEQUENCE "seq_EmailId"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE "Email" (
    "id" integer NOT NULL DEFAULT nextval('"seq_EmailId"'::regclass),
    "message" text,
    "status" "enum_emailstatus" NOT NULL,
    "createTimestamp" timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT "pk_Email" PRIMARY KEY (id)
);

ALTER SEQUENCE "seq_EmailId" OWNED BY "Email"."id";

CREATE INDEX "idx_Email_status" on "Email" USING btree( "status" );