<h2>ITP Review</h2>

<?php
$form_entries = array();

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

// used in array_map call to pull out form entry ID
function get_form_entry_id($e) {
  return $e['id'];
}

$form_seen = array_map('get_form_entry_id', $form_entries);

$review_query = get_option('rp_review_api_url') . '/entry'
  . '?key=' . get_option('rp_review_api_key')
  . '&results_per_page=300';

// find all entries in the ITP Review API
function get_review_entries($review_query) {
  $review_entries = array();
  $result = NULL;
  $ret = http_get($review_query, array('Accept' => 'application/json'));
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

$review_entries = get_review_entries($review_query);

$review_seen = array_map('get_review_entry_external_id', $review_entries);

// find any new form entries that need a corresponding entry in the Review API
$to_load = array_diff($form_seen, $review_seen);
if (!isset($to_load)) {
  $to_load = array();
}


foreach ($form_entries as $f) {
  if (in_array($f['id'], $to_load)) {
    $input = array(
      'name' => $f['1'] . ' ' . $f['2'] . ' <' . $f['3'] . '>',
      'external_id' => $f['id'],
      'collection_id' => get_option('rp_review_api_collection')
    );
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

// refresh the review entries after any new form entries were POSTed
$review_entries = get_review_entries($review_query);

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

// FIXME: hard-coded field names, layout
function render_form_entry($f, $review_entries) {
  $e = has_review_entry($f, $review_entries); 
  $output = '<tr>
<td><strong>' . $f['id'] . ': ' . $f['1'] . ' ' . $f['2'] . '</strong></td>
<div data-rp-entry-id="' . $e['id'] .'"><td>'; 
  if (isset($e['decision'])) {
    $output = $output . '<strong>Decision: ' . $e['decision']['decision'] . '</strong>';
  }
  else {
    $output = $output . '
<button type="button" data-rp-action="accept" data-rp-entry="' . $e['id'] . '" class="btn btn-success rp-button">Accept</button>
<button type="button" data-rp-action="reject" data-rp-entry="' . $e['id'] . '" class="btn btn-danger rp-button">Reject</button>
<button type="button" data-rp-action="comp" data-rp-entry="' . $e['id'] . '" class="btn btn-primary rp-button">Comp</button>';
  }

  $output = $output . '<br /><hr /><ul class="list-unstyled">
  <li><strong>Email</strong>: ' . $f['3'] . '</li>
  <li><strong>Mobile Phone</strong>: ' . $f['4'] . '</li>
  <li><strong>Location</strong>: ' . $f['5'] . '</li>
  <li><strong>Work</strong>: ' . $f['6'] . '</li>
  <li><strong>Website</strong>: ' . $f['7'] . '</li>
  <li><strong>Links</strong>: ' . $f['8'] . '</li>
  <li><strong>Wants to make/learn/do</strong>: ' . $f['9'] . '</li>
  <li><strong>Skills/knowledge/expertise</strong>: ' . $f['10'] . '</li>
  <li><strong>Wants to hear from</strong>: ' . $f['11'] . '</li>
  <li><strong>Proposed session</strong>: ' . $f['12'] . '</li>
  <li><strong>Anything else</strong>: ' . $f['13'] . '</li>
</ul>
</td>
</div>

</tr>' . "\n";
  return $output;

}
?>

<table class="table table-striped">
<?php
foreach ($form_entries as $f) {
  echo render_form_entry($f, $review_entries);
}

?>
</table>

<script type="text/javascript">
var config = {
  'paytrackUrl': '<?php echo get_option('rp_paytrack_api_url'); ?>',
  'paytrackKey': '<?php echo get_option('rp_paytrack_api_key'); ?>',
  'reviewUrl': '<?php echo get_option('rp_review_api_url'); ?>',
  'reviewKey': '<?php echo get_option('rp_review_api_key'); ?>'
};

var createDecision = function(args) {
  jQuery.ajax({
    url: '<?php echo site_url() . '/wp-content/plugins/review-plugin/api/decision'; ?>',
    data: JSON.stringify({
      'args': {
        'entry_id': args['entry'],
        'decision': args['action'],
        'reviewer': '<?php get_currentuserinfo(); echo $user_login; ?>'
      },
      'config': config
    }),
    dataType: 'json',
    type: 'POST',
    contentType: 'application/json',
    success: function(data) {
      console.dir(data);
    }
  });

}

var rpButton = function(args) {
  console.log('click ' + args['action'] + ' ' + args['entry']);
  createDecision(args);
};

jQuery(document).ready(function() {
  jQuery('button.rp-button').on('click', function() {
    rpButton({
      'action': jQuery(this).attr('data-rp-action'),
      'entry': jQuery(this).attr('data-rp-entry')
    });
  });
});

</script>
