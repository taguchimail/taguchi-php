<?php
namespace tmapiv4;

require_once("context.php");

/*
  Base class for TM record types.
*/
class Record {
    protected $resource_type;
    protected $context;
    protected $backing;

    /*
      Initiates an empty Record object.

      context: Context
          Determines the TM instance and organization to which the record 
          belongs.
    */
    public function __construct($context) {
        $this->context = $context;
        $this->resource_type = NULL;
        $this->backing = array(); 
    }

    /*
      Retreives a single Record based on its TaguchiMail identifier.

      resource_type: string
          Contains the resource type (passed in by subclass methods).
      context: Context
          Determines the TM instance and organization to query.
      record_id: string
          Contains the record's unique TaguchiMail identifier.
      parameters: associative array
          Contains additional parameters to the request.
    */
    public static function get($resource_type, $context, $record_id, $parameters) {
        $results = json_decode($context->make_request($resource_type, "GET", 
            $record_id, NULL, $parameters, NULL), true);
        $rec = new Record($context);
        $rec->backing = $results[0];
        return $rec;
    }

    /*
      Retrieve a list of Records based on a query.

      resource_type: string
          Contains the resource type (passed in by sublass methods).
      context: Context
          Determines the TM instance and organization to query.
      sort: string
          Indicates which of the record's fields should be used to sort the 
          output.
      order: string
          Contains either 'asc' or 'desc', indicating whether the result
          list should be returned in ascending or descending order.
      offset: string
          Indicates the index of the first record to be returned in the list.
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
    public static function find($resource_type, $context, $sort, $order, 
                                $offset, $limit, $query) {
        $parameters = array("sort"=>$sort, "order"=>$order, 
            "offset"=>strval($offset), "limit"=>strval($limit));
        $results = json_decode($context->make_request($resource_type, "GET",
            NULL, NULL, $parameter, $query), true);
        $records = array();
        foreach ($results as $result) {
            $rec = new Record($context);
            $rec->backing = $result;
            $records[] = $rec;
        }
        return $records;
    }

    /*
      Saves this record to the TaguchiMail database.
    */
    public function update() {
        $data = array($this->backing);
        $results = json_decode($this->context->make_request(
            $this->resource_type, "PUT", strval($this->backing["id"]), 
            json_encode($data), NULL, NULL), true);
        $this->backing = $results[0];
    }

    /*
      Creates this record in the TaguchiMail database.
    */
    public function create() {
        $data = array($this->backing);
        $results = json_decode($this->context->make_request(
            $this->resource_type, "POST", NULL, json_encode($data), NULL, 
            NULL), true);
        $this->backing = $results[0];
    }
}
?>
