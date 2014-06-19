<?php
namespace tmapiv4;

/*
  Represents a TaguchiMail connection. Must be created prior to instantiation
  of any other TaguchiMail classes, as it's a required parameter for their
  constructors.
*/
class Context {
    private $hostname;
    private $username;
    private $password;
    private $organization_id;
    private $baseURI;
    private $debug;

    /*
      The Context constructor.

      hostname: string
          Contains the hostname (or IP address) of the TaguchiMail instance
          to connect with.
      username: string
          Contains the username (email address) of an authorized user.
      password: string
          Contains the password of an authorized user.
      organization_id: string
          Indicates the organization ID to be used for creation of new objects.
          The username supplied must be authorized to access this organization.
    */
    public function __construct($hostname, $username, $password, $organization_id) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->organization_id = $organization_id;
        $this->base_uri = "https://" . $hostname . "/admin/api/" . $organization_id;
        $this->debug = false;
    }

    /* Enable cURL verbose mode for debugging */
    public function enable_debug() {
        $this->debug = true;
    }

    /* Disable cURL verbose mode */
    public function disable_debug() {
        $this->debug = false;
    }

    /*
      Makes a TaguchiMail request with a given resource, command, parameters
      and query predicates.

      resource: string
          Indicates the resource.
      command: string
          Indicates the command to issue to the resource.
      record_id: string
          Indicates the ID of the record to operate on, for record-specific
          commands.
      data: string
          Contains the JSON-formatted record data for the command, if
          required by the command type.
      parameters: associative array
          Contains additional parameters to the request. The supported
          parameters will depend on the resource and command, but commonly
          supported parameters include:
          * sort: one of the resource's fields, used to sort the result set;
          * order: either 'asc' or 'desc', determines whether the result set
            is sorted in ascending or descending order;
          * limit: positive non-zero integer indicating the maximum returned
            result set size (default to 1);
          * offset: either 0 or a positive integer indicating the position of
            the first returned result in the result set (default to 0).
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
    public function make_request($resource, $command, $record_id, $data,
                                 $parameters, $query) {
        $qs = $this->base_uri . "/" . $resource . "/";
        if ($record_id != NULL) {
            $qs .= $record_id;
        }
        $qs .= "?_method=" . urlencode($command);
        $qs .= "&auth=" . urlencode($this->username . "|" . $this->password);
        if ($query != NULL) {
            foreach ($query as $predicate) {
                $qs .= "&query=" . urlencode($predicate);
            }
        }
        if ($parameters != NULL) {
            foreach ($parameters as $key => $value) {
                $qs .= "&" . urlencode($key) . "=" . urlencode($value);
            }
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $qs);
        curl_setopt($curl, CURLOPT_USERAGENT, "TMAPIv4 PHP wrapper");
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if ($command == "GET") {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($curl, CURLOPT_POST, true);
        }

        if ($this->debug) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
        }

        // Authenticate always required, so don't wait for a 401 beforehand.
        // Work in JSON, it's smaller and faster at the TM end. In addition the
        // stats resource doesn't have an XML serialization available (tabular
        // data in XML is nasty). Set a user-agent so we can track any errors
        // occurring a little more easily.
        $headers = array("PreAuthenticate: true", "Accept: application/json");
        // Post data if it was supplied.
        if ($data != NULL && strlen($data) > 0) {
            $headers[] = "Content-Type: application/json";
            $headers[] = "Content-Length: " . strlen($data);

            if ($this->debug) {
                echo "Sending $command $qs with:\n$data\n\n";
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $rep = curl_exec($curl);
        curl_close($curl);
        return $rep;
    }
}
?>
