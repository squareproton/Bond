/** resolver
{
    "depends": [
        "logs"
    ],
    "searchPath": "logs"
}
*/

CREATE SEQUENCE "seq_Tick"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
    
CREATE SEQUENCE "seq_LogId"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
