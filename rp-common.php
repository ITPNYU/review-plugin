<?php

function rp_create_user($fname, $lname, $email, $blog) {
  $user_login_prefix = preg_replace('/\W/', '', strtolower(substr($fname, 0, 1) . $lname));
  $user_pass = wp_generate_password( $length=12, $include_standard_special_chars=false );

  $user_id = email_exists($user_email);
  if ($user_id == 'admin') {
    return;
  }
  if ($user_id) { // user already exists
    $user_info = get_userdata($user_id);
    $user_login = $user_info->user_login;
    wp_update_user(array( 'ID' => $user_id, 'user_pass' => $user_pass));
    add_user_to_blog( $blog, $user_id, "author" ) ;
  }
  else { // user does not exist
    if (username_exists( $user_login )) { // but user name is in use already
      // generate a username with random number suffix
      do {
        $user_login = $user_login_prefix . rand(1, 99);
      } while (username_exists($user_login));
    }
    else {
      $user_login = $user_login_prefix;
    }

    $user_info = array(
      'user_login' => $user_login,
      'user_pass' => $user_pass,
      'user_email' => $email,
      'first_name' => $fname,
      'last_name' => $lname,
      'nickname' => $fname . " " . $lname
    );

    $user_id = wp_insert_user( $user_info );
    if (is_wp_error($user_id)) {
      return null;
    }
    else {
      $user_info['wpid'] = $user_id;
      add_user_to_blog( $blog, $user_id, "author" ) ;
      remove_user_from_blog($user_id, 1); // hack, must manually remove from main blog
    }
  }
  return $user_info;
}

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
