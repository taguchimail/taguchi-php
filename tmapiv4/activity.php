<?php
namespace tmapiv4;

require_once("record.php");

class ActivityRevision {
    private $activity;
    private $backing;

    /*
      Creates a new activity revision, given a parent Activity and a content
      document (a.k.a. Source Document).
    */
    public function __construct($activity, $content=NULL, $revision=NULL) {
        $this->activity = $activity;
        $this->content = $content;
        if ($revision == NULL) {
            $this->backing = array();
        } else {
            $this->backing = $revision;
        }
    }

    /*
      content: Contains revision XML content. This is typically set up in the
          TaguchiMail UI, and normally includes an XML document structure based
          on RSS. However, if UI access is not required, this content can have
          an arbitrary (valid) structure; the only requirement is that the
          transform associated with the template convert this content into a
          valid intermediate MIME document.
      approval_status: If 'deployed', this revision is publicly available,
          otherwise only test events may use this revision.
      record_id: ID of the activity revision record in the database.

    */
    private static $fields = array(
        "content" => "content",
        "approval_status" => "status",
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

class Activity extends Record {
    private $existing_revisions;

    public function __construct($context) {
        parent::__construct($context);
        $this->resource_type = "activity";
        $this->existing_revisions = array();
    }

    /*
      record_id: ID of the TaguchiMail activity record.
      ref: External ID/reference, intended to store external application primary
          keys. If not NULL, the field must be unique.
      name: Activity name.
      type: This is matched up with Template types to determine what to show the
          user in the Suggested Templates/Other Templates sections.
      subtype: This can be any application-defined value.
      target_lists: This is a JSON array of list IDs to which the activity
          should be sent when queued.
      target_views: This is a JSON array of view IDs to which the activity
          distribution should be restricted.
      approval_status:  Approval workflow status of the activity; JSON object
          containing a list of locked-in deployment setting used by the queue
          command.
      deploy_datetime: Date/time at which this activity was deployed (or is
          scheduled to deploy).
      template_id: The ID of the Template this activity uses.
      campaign_id: The ID of the Campaign to which this activity belongs.
      throttle: Maximum deployment rate of this message, in messages per minute.
          If 0, the activity is suspended. Web pages (an other pull messages)
          ignore this value.
      xml_data: Arbitrary application XML data store.
      status: Activity status; leave NULL if not used.
    */
    private static $fields = array(
        "record_id" => "id",
        "ref" => "ref",
        "name" => "name",
        "type" => "type",
        "subtype" => "subtype",
        "target_lists" => "target_lists",
        "target_views" => "target_views",
        "approval_status" => "approval_status",
        "deploy_datetime" => "date",
        "template_id" => "template_id",
        "campaign_id" => "campaign_id",
        "throttle" => "throttle",
        "xml_data" => "data",
        "status" => "status",
        "latest_revision" => "latest_revision"
    );

    public function __get($name) {
        if ($name == "latest_revision") {
            // Latest activity revision content. If set, a new revision will be
            // created upon activity create/update.
            if (count($this->backing["revisions"]) > 0) {
                return new ActivityRevision($this, NULL, $this->backing["revisions"][0]);
            } elseif (count($this->existing_revisions) > 0) {
                return new ActivityRevision($this, NULL, $this->existing_revisions[0]);
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
                $revision = array("content" => $value->content);
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
      Saves this activity to the TaguchiMail database.
    */
    public function update() {
        parent::update();
        // Need to move the existing revisions to avoid re-creating the same
        // ones if this object is saved again.
        $this->existing_revisions = $this->backing["revisions"];
        $this->backing["revisions"] = array();
    }

    /*
      Creates this activity in the TaguchiMail database.
    */
    public function create() {
        parent::create();
        // Need to move the existing revisions to avoid re-creating the same
        // ones if this object is saved again.
        $this->existing_revisions = $this->backing["revisions"];
        $this->backing["revisions"] = array();
    }

    /*
      Sends a proof message for an activity record to the list with the
      specified ID/to a specific list.

      proof_list: str/SubscriberList
          Indicates List ID of the proof list/the list to which the messages
          will be sent.
      subject_tag: str
          Displays at the start of the subject line.
      custom_message: str
          Contains a custom message which will be included in the proof
          header.
    */
    public function proof($proof_list, $subject_tag, $custom_message) {
        if ($proof_list instanceof SubscriberList) {
            $this->proof($proof_list->record_id, $subject_tag, $custom_message);
        } else {
            $data = array("id" => $this->record_id, "list_id" => $proof_list,
                "tag" => $subject_tag, "message" => $custom_message);
            $this->context->make_request($this->resource_type, "PROOF",
                strval($this->backing["id"]), json_encode(array($data)), NULL,
                NULL);
        }
    }

    /*
      Sends an approval request for an activity record to the list with the
      specified ID/to a specific list.

      approval_list: str/SubscriberList
          Indicates List ID of the approval list/the list to which the
          approval request will be sent.
      subject_tag:
          Displays at the start of the subject line.
      custom_message:
          Contains a custom message which will be included in the approval
          header.
    */
    public function request_approval($approval_list, $subject_tag, $custom_message) {
        if ($approval_list instanceof SubscriberList) {
            $this->request_approval($approval_list->record_id, $subject_tag,
                $custom_message);
        } else {
            $data = array("id" => $this->record_id, "list_id" => $approval_list,
                "tag" => $subject_tag, "message" => $custom_message);
            $this->context->make_request($this->resource_type, "APPROVAL",
                strval($this->backing["id"]), json_encode(array($data)), NULL,
                NULL);
        }
    }

    /*
      Triggers the activity, causing it to be delivered to a specified list
      of subscribers.

      subscribers: numeric list
          Contains subscriber IDs/subscribers to whom the message should be
          delivered.
      request_content: str
          XML content for message customization. The request_content document
          is available to the activity template's stylesheet, in addition to
          the revision's content. Should be NULL if unused.
      test: boolean
          Determines whether or not to treat this as a test send.
    */
    public function trigger($subscribers, $request_content, $test) {
        if ($subscribers[0] instanceof Subscriber) {
            $subscriber_ids = array();
            foreach ($subscribers as $s) {
                $subscriber_ids[] = $s->record_id;
            }
            $this->trigger($subscriber_ids, $request_content, $test);
        } else {
            $data = array("id" => $this->record_id, "test" => $test ? 1 : 0,
                "request_content" => $request_content,
                "conditions" => $subscribers);
            $this->context->make_request($this->resource_type, "TRIGGER",
                strval($this->backing["id"]), json_encode(array($data)), NULL,
                NULL);
        }
    }

    /*
      Retreives a single Activity based on its TaguchiMail identifier.

      context: Context
          Determines the TM instance and organization to query.
      record_id: string
          Contains the record's unique TaguchiMail identifier.
    */
    public static function get($context, $record_id, $parameters, $_ = NULL) {
        // $_ is not used; just to allow overloading with get() in Record.
        $results = json_decode($context->make_request("activity", "GET",
            $record_id, NULL, $parameters, NULL), true);
        $rec = new Activity($context);
        $rec->backing = $results[0];
        $rec->existing_revisions = $rec->backing["revisions"];
        // Clear out existing revisions so they're not sent back to the server
        // on update.
        $rec->backing["revisions"] = array();
        return $rec;
    }

    /*
      Retrieves a single Activity based on its TaguchiMail identifier, with
      its latest revision content.

      context: Context
          Determines the TM instance and organization to query.
      record_id: str
          Contains the list's unique TaguchiMail identifier.
    */
    public static function get_with_content($context, $record_id, $parameters) {
        $new_params = array("revision" => "latest");
        return Activity::get($context, $record_id, $new_params);
    }

    /*
      Retrieves a list of Activities based on a query.

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
        $results = json_decode($context->make_request("activity", "GET", NULL,
            NULL, $parameters, $query), true);
        $records = array();
        foreach ($results as $result) {
            $rec = new Activity($context);
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
