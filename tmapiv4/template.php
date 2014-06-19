<?php
namespace tmapiv4;

require_once("record.php");

class TemplateRevision {
    private $template;
    private $revision;

    /*
      Creates a new template revision, given a parent Template, a format
      document (a.k.a. Data Description), and a content document (XSL
      stylesheet).
    */
    public function __construct($template, $format=NULL, $content=NULL,
                                $revision=NULL) {
        $this->template = $template;
        $this->format = $format;
        $this->content = $content;
        if ($revision == NULL) {
            $this->backing = array();
        } else {
            $this->backing = $revision;
        }
    }

    /*
      format: Contains the revision's Data Description, which controls the form
          interface created by TaguchiMail to edit activities using this
          template. This document indirectly determines the structure of the
          source document used by the template's stylesheet.
      content: Contains the revision XSLT stylesheet. This is normally created
          within the TaguchiMail UI, and is designed to work with the XML
          documents created by the activity edit interface; these are created
          based on the format field, which defines allowable data types and
          document structure.
    */
    private static $fields = array(
        "content" => "content",
        "format" => "format",
        "record_id" => "id"
    );

    public function __get($name) {
        return strval($this->backing[self::$fields[$name]]);
    }

    public function __set($name, $value) {
        if (array_key_exists($name, self::$fields) && $name != "record_id") {
            if ($name == "content") {
                $this->backing["format"] = $value;
            } else {
                $this->backing[$name] = $value;
            }
        }
    }
}

class Template extends Record {
    private $existing_revisions;

    public function __construct($context) {
        parent::__construct($context);
        $this->resource_type = "template";
        $this->existing_revisions = array();
    }

    /*
      record_id: ID of the TaguchiMail activity record.
      ref: External ID/reference, intended to store external application primary
          keys. If not NULL, the field must be unique.
      name: Template name.
      type: This is matched up with Activity types to determine what to show the
          user in the Suggested Templates/Other Templates sections.
      subtype: This can be any application-defined value.
      xml_data: Arbitrary application XML data store.
      status: Template status; leave NULL if not used.
    */
    private static $fields = array(
        "record_id" => "id",
        "ref" => "ref",
        "name" => "name",
        "type" => "type",
        "subtype" => "subtype",
        "xml_data" => "data",
        "status" => "status",
        "latest_revision" => "latest_revision"
    );

    public function __get($name) {
        if ($name == "latest_revision") {
            // Latest template revision content. If set, a new revision will be
            // created upon activity create/update.
            if (count($this->backing["revisions"]) > 0) {
                return new TemplateRevision($this, NULL, $this->backing["revisions"][0]);
            } elseif (count($this->existing_revisions) > 0) {
                return new TemplateRevision($this, NULL, $this->existing_revisions[0]);
            } else {
                return NULL;
            }
        } else {
            return strval($this->backing[self::$fields[$name]]);
        }
    }

    public function __set($name, $value) {
        if (array_key_exists($name, self::$fields) && $name != "record_id" &&
            $name != "status") {
            if ($name == "latest_revision") {
                $revision = array("content" => $value->content,
                    "format" => $value->format);
                if (count($this->backing["revisions"]) > 0) {
                    $this->backing["revisions"][0] = $revision;
                } else {
                    $this->backing["revisions"][] = $revision;
                }
            } else {
                $this->backing[$name] = $value;
            }
        }
    }

    /*
      Saves this template to the TaguchiMail database.
    */
    public function update() {
        parent::update();
        // Need to move the existing revisions to avoid re-creating the same
        // ones if this object is saved again.
        $this->existing_revisions = $this->backing["revisions"];
        $this->backing["revisions"] = array();
    }

    /*
      Creates this template in the TaguchiMail database.
    */
    public function create() {
        parent::create();
        // Need to move the existing revisions to avoid re-creating the same
        // ones if this object is saved again.
        $this->existing_revisions = $this->backing["revisions"];
        $this->backing["revisions"] = array();
    }

    /*
      Retreives a single Template based on its TaguchiMail identifier.

      context: Context
          Determines the TM instance and organization to query.
      record_id: string
          Contains the record's unique TaguchiMail identifier.
    */
    public static function get($context, $record_id, $parameters, $_ = NULL) {
        // $_ is not used; just to allow overloading with get() in Record.
        $results = json_decode($context->make_request("template", "GET",
            $record_id, NULL, $parameters, NULL), true);
        $rec = new Template($context);
        $rec->backing = $results[0];
        $rec->existing_revisions = $rec->backing["revisions"];
        // Clear out existing revisions so they're not sent back to the server
        // on update.
        $rec->backing["revisions"] = array();
        return $rec;
    }

    /*
      Retrieves a single Template based on its TaguchiMail identifier, with
      its latest revision content.

      context: Context
          Determines the TM instance and organization to query.
      record_id: str
          Contains the list's unique TaguchiMail identifier.
    */
    public static function get_with_content($context, $record_id, $parameters) {
        $new_params = array("revision" => "latest");
        return Template::get($context, $record_id, $new_params);
    }

    /*
      Retrieves a list of Templates based on a query.

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
        $results = json_decode($context->make_request("template", "GET", NULL,
            NULL, $parameters, $query), true);
        $records = array();
        foreach ($results as $result) {
            $rec = new Template($context);
            $rec->backing = $result;
            $rec->existing_revisions = $rec->backing["revisions"];
            // Clear out existing revisions so they're not sent back to the
            // server on update.
            $rec->backing["revisions"] = array();
            $records[] = $rec;
        }
        return $records;
    }
}
?>
