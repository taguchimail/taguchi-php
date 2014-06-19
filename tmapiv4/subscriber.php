<?php
namespace tmapiv4;

require_once("record.php");
require_once("subscriber_list.php");

class Subscriber extends Record {

    /*
      Instantiates an empty Subscriber object.

      context: Context
          Determines the TM instance and organization to which the
          subscriber belongs.
    */
    public function __construct($context) {
        parent::__construct($context);
        $this->resource_type = "subscriber";
    }

    /*
      record_id: ID of the TaguchiMail subscriber record.
      ref: External ID/reference, intended to store external application
          primary keys. If not NULL, the field must be unique.
      title: Title (Mr, Mrs etc).
      firstname: First (given) name.
      lastname: Last (family) name.
      notifications: Notificaton field, can store arbitrary application data.
      extra: Extra field, can store arbitrary application data.
      phone: Phone number.
      dob: Date of birth.
      address: Postal address line 1.
      address2: Postal address line 2.
      address3: Postal address line 3.
      suburb: Postal address city, suburb or locality.
      state: Postal adress state or region.
      country: Postal address country.
      postcode: Postal code.
      gender: Gender (M/F, mail/female).
      email: Email address. If external ID is NULL, this must be unique.
      social_rating: Social media influence rating. Ordinal positive integer
          scale; higher values mean more public profile data, more status
          updates, and/or more friends. Read-only as this is calculated by
          TaguchiMail's social media subsystem.
      social_profile: Social media aggregate profile. JSON data structure
          similar to the OpenSocial v1.1 Person schema.
      unsubscribe_datetime: Date/time at which this subscriber globally
          unsubscribed (or NULL).
      bounce_datetime: Date/time at which this subscriber's email address was
          marked as invalid (or NULL).
      xml_data: Arbitrary application XML data store.
    */
    private static $fields = array(
        "record_id" => "id",
        "ref" => "ref",
        "title" => "title",
        "firstname" => "firstname",
        "lastname" => "lastname",
        "notifications" => "notifications",
        "extra" => "extra",
        "phone" => "phone",
        "dob" => "dob",
        "address" => "address",
        "address2" => "address2",
        "address3" => "address3",
        "suburb" => "suburb",
        "state" => "state",
        "country" => "country",
        "postcode" => "postcode",
        "gender" => "gender",
        "email" => "email",
        "social_rating" => "social_rating",
        "social_profile" => "social_profile",
        "unsubscribe_datetime" => "unsubscribed",
        "bounce_datetime" => "bounced",
        "xml_data" => "data"
    );

    public function __get($name) {
        return strval($this->backing[self::$fields[$name]]);
    }

    public function __set($name, $value) {
        if (array_key_exists($name, self::$fields) && $name != "record_id") {
            $this->backing[$name] = $value;
        }
    }

    /*
      Retrieves a custom field value by field name.

      field: string
          Indicates the custom field to retrieve.
    */
    public function get_custom_field($field) {
        if (!array_key_exists("custom_fields", $this->backing) ||
            $this->backing["custom_fields"] == NULL) {
            $this->backing["custom_fields"] = array();
        }
        foreach ($this->backing["custom_fields"] as $key => $value) {
            if (strval($key) == $field) {
                return str($value);
            }
        }
        return NULL;
    }

    /*
      Sets a custom field value by field.

      field: string
          Contains the name of the field to set. If a field with that name
          is already defined for this subscriber, the new value will overwrite
          the old one.
      data: string
          Contains the field's data. If a field is intended to store array or
          other complex data types, this should be JSON-encoded (or serialized
          to XML depending on application preference).
    */
    public function set_custom_field($field, $data) {
        if (!array_key_exists("custom_fields", $this->backing) ||
            $this->backing["custom_fields"] == NULL) {
            $this->backing["custom_fields"] = array();
        }
        for ($i = 0; $i < count($this->backing["custom_fields"]); $i++) {
            if (strval($this->backing["custom_fields"][$i]["field"]) == $field) {
                $this->backing["custom_fields"][$i]["data"] = $data;
                return;
            }
        }
        // Field was not found in the array, so add it.
        $cf = array("field" => $field, "data" => $data);
        $this->backing["custom_fields"][] = $cf;
    }

    /*
      Checks the subscription status of a specific list.

      list: string/SubscriberList
          Contains the list ID/list to check subscription status for.
    */
    public function is_subscribed_to_list($list) {
        if ($list instanceof SubscriberList) {
            return $this->is_subscribed_to_list($list->record_id);
        } else {
            if (!array_key_exists("lists", $this->backing) ||
                $this->backing["lists"] == NULL) {
                $this->backing["lists"] = array();
            }
            foreach ($this->backing["lists"] as $record) {
                if (strval($record["list_id"]) == $list) {
                    if (strval($record["unsubscribed"]) == NULL) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            return false;
        }
    }

    /*
      Retrieves teh subscription option (arbitrary application data) for a
      specific list.

      list: string/SubscriberList
          Contains the list ID/list to retrieve subscription option for.
    */
    public function get_subscription_option($list) {
        if ($list instanceof SubscriberList) {
            return $this->get_subscription_option($list->record_id);
        } else {
            if (!array_key_exists("lists", $this->backing) ||
                $this->backing["lists"] == NULL) {
                $this->backing["lists"] = array();
            }
            foreach ($this->backing["lists"] as $record) {
                if (strval($record["list_id"]) == $list) {
                    return strval($record["option"]);
                }
            }
            return NULL;
        }
    }

    /*
      Checks the unsubscription status of a specific list.

      list: string/SubscriberList
          Contains the list ID/list to check unsubscription status for.
    */
    public function is_unsubscribed_from_list($list) {
        if ($list instanceof SubscriberList) {
            return $this->is_subscribed_from_list($list->record_id);
        } else {
            if (!array_key_exists("lists", $this->backing) ||
                $this->backing["lists"] == NULL) {
                $this->backing["lists"] = array();
            }
            foreach ($this->backing["lists"] as $record) {
                if (strval($record["list_id"]) == $list) {
                    if (strval($record["unsubscribed"]) == NULL) {
                        return false;
                    } else {
                        return true;
                    }
                }
            }
            return NULL;
        }
    }

    /*
      Retrieves all lists to which this record is subscribed.
    */
    public function get_subscribed_list_ids() {
        if (!array_key_exists("lists", $this->backing) ||
            $this->backing["lists"] == NULL) {
            $this->backing["lists"] = array();
        }
        $lists = array();
        foreach ($this->backing["lists"] as $list) {
            if ($list["unsubscribed"] == NULL) {
                $lists[] = strval($list["list_id"]);
            }
        }
        return $lists;
    }

    public function get_subscribed_lists() {
        $list_ids = $this->get_subscribed_list_ids();
        $lists = array();
        foreach ($list_ids as $list_id) {
            $lists[] = SubscriberList::get($this->context, $list_id, NULL);
        }
        return $lists;
    }

    /*
      Retrieves all lists to which this record is unsubscribed.
    */
    public function get_unsubscribed_list_ids() {
        if (!array_key_exists("lists", $this->backing) ||
            $this->backing["lists"] == NULL) {
            $this->backing["lists"] = array();
        }
        $lists = array();
        foreach ($this->backing["lists"] as $list) {
            if ($list["unsubscribed"] != NULL) {
                $lists[] = strval($list["list_id"]);
            }
        }
        return $lists;
    }

    public function get_unsubscribed_lists() {
        $list_ids = $this->get_subscribed_list_ids();
        $lists = array();
        foreach ($list_ids as $list_id) {
            $lists[] = SubscriberList::get($this->context, $list_id, NULL);
        }
        return $lists;
    }

    /*
      Adds the subscriber to a specific list, resetting the unsubscribe flag
      if previously set.

      list: string/SubscriberList
          Contains the list ID/list which should be added.
      option: string
          Contains the list subscription option (arbitrary application data).
    */
    public function subscribe_to_list($list, $option) {
        if ($list instanceof SubscriberList) {
            $this->subscribe_to_list($list->record_id, $option);
        } else {
            if (!array_key_exists("lists", $this->backing) ||
                $this->backing["lists"] == NULL) {
                $this->backing["lists"] = array();
            }
            for ($i = 0; $i < count($this->backing["lists"]); $i++) {
                if (strval($this->backing["lists"][$i]["list_id"]) == $list) {
                    $this->backing["lists"][$i]["option"] = $option;
                    $this->backing["lists"][$i]["unsubscribed"] = NULL;
                    return;
                }
            }
            // List was not found in the array, so add it.
            $list = array("list_id" => intval($list), "option" => $option);
            $this->backing["lists"][] = $list;
        }
    }

    /*
      Unsubscribe from a specific list, adding the list if not already
      subscribed.

      list: string/SubscriberList
          Contains the list ID/list from which the record should be unsubscribed.
    */
    public function unsubscribe_from_list($list) {
        if ($list instanceof SubscriberList) {
            $this->unsubscribe_from_list($list->record_id);
        } else {
            if (!array_key_exists("lists", $this->backing) ||
                $this->backing["lists"] == NULL) {
                $this->backing["lists"] = array();
            }
            for ($i = 0; $i < count($this->backing["lists"]); $i++) {
                if (strval($this->backing["lists"][$i]["list_id"]) == $list &&
                    $this->backing["lists"][$i]["unsubscribed"] == NULL) {
                    $this->backing["lists"][$i]["unsubscribed"] = true;
                    return;
                }
            }
            // List was not found in the array, so add it.
            $list = array("list_id" => intval($list));
            $this->backing["lists"][] = $list;
        }
    }

    /*
      Creates this record in the TaguchiMail database if it doesn't already
      exist (based on a search for records with the same ref of email in that
      order). If it does, simply update what's already in the database. Fields
      not written to the backing store (via property update) will not be over-
      written in the database.
    */
    public function create_or_update() {
        $data = array($this->backing);
        $results = json_decode($this->context->make_request($this->resource_type,
            "CREATEORUPDATE", NULL, json_encode($data), NULL, NULL), true);
        $this->backing = $results[0];
    }

    /*
      Retreives a single Subscriber based on its TaguchiMail identifier.

      context: Context
          Determines the TM instance and organization to query.
      record_id: string
          Contains the record's unique TaguchiMail identifier.
    */
    public static function get($context, $record_id, $parameters, $_ = NULL) {
        // $_ is not used; just to allow overloading with get() in Record.
        $results = json_decode($context->make_request("subscriber", "GET",
            $record_id, NULL, $parameters, NULL), true);
        $rec = new Subscriber($context);
        $rec->backing = $results[0];
        return $rec;
    }

    /*
      Retrieves a list of Subscribers based on a query.

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
        $results = json_decode($context->make_request("subscriber", "GET", NULL,
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
