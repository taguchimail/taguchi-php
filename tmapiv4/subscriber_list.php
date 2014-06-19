<?php
namespace tmapiv4;

require_once("record.php");

class SubscriberList extends Record {

    public function __construct($context) {
        parent::__construct($context);
        $this->resource_type = "list";
    }

    /*
      record_id: ID of the TaguchiMail list record.
      ref: External ID/reference, intended to store external application
          primary keys. If not None, the field must be unique.
      name: List name.
      type: List type; if set to "proof", "approval" or "notification" this list
          becomes a utility list accessible only via settings; if set to another
          not-null vlaue the list is hidden from the UI buy may still be used by
          API methods; if None, the list is a public opt-in list. Leave None if
          not used.
      creation_datetime: Date/time at which this list was created.
      xml_data: Arbitrary application XML data store.
      status: List status; leave NULL if not used.
    */
    private static $fields = array(
        "record_id" => "id",
        "ref" => "ref",
        "name" => "name",
        "type" => "type",
        "creation_datetime" => "timestamp",
        "xml_data" => "data",
        "status" => "status"
    );

    public function __get($name) {
        return strval($this->backing[self::$fields[$name]]);
    }

    public function __set($name, $value) {
        if (array_key_exists($name, self::$fields) && $name != "record_id" &&
            $name != "creation_datetime" && $name != "status") {
            $this->backing[$name] = $value;
        }
    }

    /*
      Adds a subscriber to this list with an application-defined subscription
      option.

      subscriber: Subscriber
          A subscriber to add.
      option: string
          A subscription option.
    */
    public function subscribe_subscriber($subscriber, $option) {
        $subscriber->subscribe_to_list($this, $option);
    }

    /*
      Unsubscribes a subscriber from this list (adding it first if necessary).

      subscriber: Subscriber
          A subscriber to unsubscribe.
    */
    public function unsubscribe_subscriber($subscriber) {
        $subscriber->unsubscribe_from_list($this);
    }

    /*
      Retrieves (limit) subscribers to this list (regardless of opt-in/opt-out
      status), starting with the (offset)th subscriber.
    */
    public function get_subscribers($offset, $limit) {
        return Subscriber::find($this->context, "id", "asc", $offset, $limit,
            array("list_id-eq-" . $this->record_id));
    }

    /*
      Retreives a single SubscriberList based on its TaguchiMail identifier.

      context: Context
          Determines the TM instance and organization to query.
      record_id: string
          Contains the record's unique TaguchiMail identifier.
    */
    public static function get($context, $record_id, $parameters, $_ = NULL) {
        // $_ is not used; just to allow overloading with get() in Record.
        $results = json_decode($context->make_request("list", "GET",
            $record_id, NULL, $parameters, NULL), true);
        $rec = new SubscriberList($context);
        $rec->backing = $results[0];
        return $rec;
    }

    /*
      Retrieve a list of SubscriberLists based on a query.

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
        $results = json_decode($context->make_request("list", "GET", NULL,
            NULL, $parameters, $query), true);
        $records = array();
        foreach ($results as $result) {
            $rec = new SubscriberList($context);
            $rec->backing = $result;
            $records[] = $rec;
        }
        return $records;
    }
}
?>
