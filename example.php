<?php
require_once("tmapiv4/subscriber.php");
require_once("tmapiv4/activity.php");

/* Contact support@taguchi.com.au for these details */
$connection_params = array(
    "host" => "your.taguchi.host",
    "organization_id" => null,
    "username" => "your-taguchi-account@example.org",
    "password" => "secret"
);
$ctx = new tmapiv4\Context(
    $connection_params["host"], $connection_params["username"],
    $connection_params["password"], $connection_params["organization_id"]
);

/*
These are optional -- get the appropriate List and Activity IDs from the
Taguchi admin UI.
*/
$list_id = null;
$activity_id = null;

/*
Create a new subscriber profile (updating the first and last name fields if
a profile with that email address already exists), and add it to the list if
the ID has been specified.
*/
$subscriber = new tmapiv4\Subscriber($ctx);
$subscriber->firstname = "John";
$subscriber->lastname = "Doe";
$subscriber->email = "john.doe@example.org";
if ($list_id) {
    $subscriber->subscribe_to_list($list_id, null);
}
$subscriber->create_or_update();

/*
Send an email to the subscriber's address if $activity_id has been specified.

$custom_content is either null or a string containing any arbitrary (valid)
XML document with data specific to this email.
*/
if ($activity_id) {
    $custom_content = null;
    $email_to_send = tmapiv4\Activity::get($ctx, $activity_id, NULL);
    $email_to_send->trigger(array($subscriber->record_id), $custom_content,
                            false);
}
?>
