<?php
namespace tmapiv4;

require_once("record.php");

class Campaign extends Record {

    /*
      Instantiates an empty Subscriber object.

      context: Context
          Determines the TM instance and organization to which the 
          subscriber belongs.
    */
    public function __construct($context) {
        parent::__construct($context);
        $this->resource_type = "campaign";
    }

    /*
      record_id: ID of the TaguchiMail campaign record.
      ref: External ID/reference, intended to store external application primary 
          keys. If not NULL, the field must be unique.
      name: Campaign name.
      start_datetime: Date/time at which this campaign started (or is scheduled 
          to start).
      xml_data: Arbitrary application XML data store.
      status: Campaign status; leave NULL if not used.
    */
    private static $fields = array(
        "record_id" => "id",
        "ref" => "ref",
        "name" => "name",
        "start_datetime" => "date",
        "xml_data" => "data",
        "status" => "status"
    );

    public function __get($name) {
        return strval($this->backing[self::$fields[$name]]);
    }

    public function __set($name, $value) {
        if (array_key_exists($name, self::$fields) && $name != "record_id" && 
            $name != "status") {
            $this->backing[$name] = $value;
        }
    }

    /*
      Retreives a single Campaign based on its TaguchiMail identifier.

      context: Context
          Determines the TM instance and organization to query.
      record_id: string
          Contains the record's unique TaguchiMail identifier.    
    */
    public static function get($context, $record_id, $parameters, $_ = NULL) {
        // $_ is not used; just to allow overloading with get() in Record.
        $results = json_decode($context->make_request("campaign", "GET",
            $record_id, NULL, $parameters, NULL), true);
        $rec = new Campaign($context);
        $rec->backing = $results[0];
        return $rec;
    }

    /*
      Retrieves a list of Campaigns based on a query.

      context: Context
          Determines the TM instance and organization to query.
      sort: string
          Indicates which of the record's fields should be used to sort 
          the output.
      order: string
          Contains either 'asc' or 'desc', indicating whether the result
          list should be returned in ascending or descending order.
      offset: string
          Indicates the index of the first record to be returned in the 
          list.
      limit: string
          Indicates the maximum number of records to return.
      query: numeric array
          Contains query predicates, each of the form: [field]-[operator]-
          [value] where [field] is one of the defined resource fields, 
          [operator] is one of the below-listed comparison operators, and 
          [value] is a string value to which the field should be compared.

          Supported operators:
          * eq: mapped to SQL '=', test for equality between [field] and 
            [value] (case-sensitive for strings);
          * neq: mapped to SQL '!=', test for inequality between [field] and 
            [value] (case-sensitive for strings);
          * lt: mapped to SQL '<', test if [field] is less than [value];
          * gt: mapped to SQL '>', test if [field] is greater than [value];
          * lte: mapped to SQL '<=', test if [field] is less than or equal to 
            [value];
          * gte: mapped to SQL '>=', test if [field] is greater than or equal 
            to [value];
          * re: mapped to PostgreSQL '~', interprets [value] as POSIX regular
            expression and test if [field] matches it;
          * rei: mapped to PostgreSQL '~*', performs a case-insensitive POSIX
            regular expression match;
          * like: mapped to SQL 'LIKE' (case-sensitive);
          * is: mapped to SQL 'IS', should be used to test for NULL values in
            the database as [field]-eq-null is always false;
          * nt: mapped to SQL 'IS NOT', should be used to test for NOT NULL
            values in the database as [field]-neq-null is always false.
    */
    public static function find($context, $sort, $order, $offset, $limit, $query, $_=NULL) {
        // $_ is not used; just to allow overloading with find() in Record.
        $parameters = array("sort" => $sort, "order" => $order,
            "offset" => strval($offset), "limit" => strval($limit));
        $results = json_decode($context->make_request("campaign", "GET", NULL,
            NULL, $parameters, $query), true);
        $records = array();
        foreach ($results as $result) {
            $rec = new Subscriber($context);
            $rec->backing = $result;
            $records[] = $rec;
        }
        return $records;
    }
}
?>
