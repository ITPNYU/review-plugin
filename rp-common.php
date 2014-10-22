<?php

// used in array_map call to pull out form entry ID
function get_form_entry_id($e) {
  return $e['id'];
}

// find all entries in the ITP Review API
function get_review_entries($review_query) {
  $review_entries = array();
  $result = NULL;
  $filters = urlencode(json_encode(array(
      'filters' => array(
        array(
          'name' => 'collection_id',
          'op' => 'eq',
          'val' => get_option('rp_review_api_collection')
        )
      )
    ))
  );
  $review_query_collection = $review_query . '&q=' . $filters;
  $ret = http_get($review_query_collection, array('Accept' => 'application/json'));
  if ($ret != FALSE) {
    $result = json_decode(http_parse_message($ret)->body, TRUE);
  }
  if (isset($result) && isset($result['objects'])) {
    foreach ($result['objects'] as $e) {
      array_push($review_entries, $e);
    }
  }
  return $review_entries;
}

// used in array_map call to pull out review entry external ID (which is form entry ID)
function get_review_entry_external_id($e) {
  return $e['external_id'];
}

function has_decision($f, $review_entries) {
  foreach ($review_entries as $r) {
    if (($r['external_id'] == $f['id']) && isset($r['decision'])) {
      return $r['decision'];
    }
  }
  return NULL;
}

function has_review_entry($f, $review_entries) {
  foreach ($review_entries as $r) {
    if ($r['external_id'] == $f['id']){
      return $r;
    }
  }
  return NULL;
}

function rp_parse_opt($obj, $option) {
  $result = array();

  foreach ($obj as $k => $v) {
    if (isset($v) && !empty($v) && (ereg('^' . $option . '\.', $k))) {
      array_push($result, $v);
    }
  }
  return implode(', ', $result);
}

?>
