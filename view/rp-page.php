<h2>ITP Review</h2>

<?php
$form_entries = array();
$review_entries = array();

// find all entries in Gravity for the target form
$gravity_query = rp_gravity_query('forms/' . get_option('rp_gravity_form') . '/entries');
$result = NULL;
$ret = http_get($gravity_query, array('Accept' => 'application/json'));
if ($ret != FALSE) {
  $result = json_decode(http_parse_message($ret)->body, TRUE);
}
if (isset($result) && isset($result['response']) && isset($result['response']['entries'])) {
  foreach ($result['response']['entries'] as $e) {
    array_push($form_entries, $e);
  }
}

function rp_form_seen_callback($e) {
  return $e['id'];
}

$form_seen = array_map('rp_form_seen_callback', $form_entries);

// find all entries in the ITP Review API
$review_query = get_option('rp_review_api_url') . '/entry' . '?key=' . get_option('rp_review_api_key');
$result = NULL;
$ret = http_get($rp_review_query, array('Accept' => 'application/json'));
if ($ret != FALSE) {
  $result = json_decode(http_parse_message($ret)->body, TRUE);
}
if (isset($result) && isset($result['objects'])) {
  foreach ($result['objects'] as $e) {
    array_push($review_entries, $e);
  }
}

function rp_review_seen_callback($e) {
  return $e['entry_id'];
}
$review_seen = array_map('rp_review_seen_callback', $review_entries);

// find any new form entries that need a corresponding entry in the Review API
$to_load = array_diff($form_seen, $review_seen);

foreach ($form_entries as $f) {
  if (!in_array($f['id'], $to_load)) {
    $result = NULL;
    $ret = http_post_data($review_query, 
      json_encode($input),
      array('headers' => array('Content-Type' => 'application/json'))
    );
    if ($ret != FALSE) {
      $result = json_decode(http_parse_message($ret)->body, TRUE);
    }
  }
}

?>
