<?php
/*

REQUIREMENTS

* A custom slash command on a Slack team
* A web server running PHP5 with cURL enabled

USAGE

* Place this script on a server running PHP5 with cURL.
* Set up a new custom slash command on your Slack team:
  http://my.slack.com/services/new/slash-commands
* Under "Choose a command", enter whatever you want for
  the command. /isitup is easy to remember.
* Under "URL", enter the URL for the script on your server.
* Leave "Method" set to "Post".
* Decide whether you want this command to show in the
  autocomplete list for slash commands.
* If you do, enter a short description and usage hint.

*/

# Grab some of the values from the slash command, create vars for post back to Slack
$command = $_POST['command'];
$text = $_POST['text'];
$token = $_POST['token'];
$channel_id = $_POST['channel_id'];
$user_name = $_POST['user_name'];

// Webhook for response type
$slack_webhook_url = 'https://hooks.slack.com/services/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';

# Check the token and make sure the request is from our team
if ($token != 'x4GuxFrOYiZxYgTTP9ZfDMxG') { #replace this with the token from your slash command configuration page
    $msg = "The token for the slash command doesn't match. Check your script.";
    die($msg);
}

# isitup.org doesn't require you to use API keys, but they do require that any automated script send in a user agent string.
# You can keep this one, or update it to something that makes more sense for you
$user_agent = "IsitupForSlack/1.0 (https://github.com/mccreath/istiupforslack; mccreath@gmail.com)";

# We're just taking the text exactly as it's typed by the user. If it's not a valid domain, isitup.org will respond with a `3`.
# We want to get the JSON version back (you can also get plain text).
$url_to_check = "https://isitup.org/" . $text . ".json";

# Set up cURL
$ch = curl_init($url_to_check);

# Set up options for cURL
# We want to get the value back from our query
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
# Send in our user agent string
curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

# Make the call and get the response
$ch_response = curl_exec($ch);
# Close the connection
curl_close($ch);

# Decode the JSON array sent back by isitup.org
$response_array = json_decode($ch_response, true);

# Build our response
# Note that we're using the text equivalent for an emoji at the start of each of the responses.
# You can use any emoji that is available to your Slack team, including the custom ones.
if ($ch_response === FALSE) {
    # isitup.org could not be reached
    $reply['response_type'] = 'ephemeral';
    $reply['text'] = 'Ironically, isitup could not be reached.';
} else {
    $reply['response_type'] = 'in_channel';
    if ($response_array['status_code'] == 1) {
        # Yay, the domain is up!
        $reply['text'] = ":thumbsup: I am happy to report that *<http://" . $response_array["domain"] . "|" . $response_array["domain"] . ">* is *up*!";
    } else if ($response_array["status_code"] == 2) {
        # Boo, the domain is down.
        $reply['text'] = ":disappointed: I am sorry to report that *<http://" . $response_array["domain"] . "|" . $response_array["domain"] . ">* is *not up*!";
    } else if ($response_array["status_code"] == 3) {
        # Uh oh, isitup.org doesn't think the domain entered by the user is valid
        $reply['text'] = ":interrobang: *" . $text . "* does not appear to be a valid domain. \n";
        $reply['text'] .= "Please enter both the domain name AND suffix (example: *amazon.com* or *whitehouse.gov*).";
    }
}

$data = [
    'username' => 'Test bot',
    'channel' => $channel_id,
    'text' => $reply['text'],
    'mrkdwn' => true,
    'response_type' => $reply['response_type']
];

# Send the reply back to the user. 
$json_string = json_encode($reply);

$slack_call = curl_init($slack_webhook_url);
curl_setopt($slack_call, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($slack_call, CURLOPT_POSTFIELDS, $json_string);
curl_setopt($slack_call, CURLOPT_CRLF, true);
curl_setopt($slack_call, CURLOPT_RETURNTRANSFER, true);
curl_setopt($slack_call, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Content-Length: " . strlen($json_string))
);

$result = curl_exec($slack_call);
curl_close($slack_call);
