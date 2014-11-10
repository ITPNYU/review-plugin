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

$form_seen = array_map('get_form_entry_id', $form_entries);

$review_query = get_option('rp_review_api_url') . '/entry'
  . '?key=' . get_option('rp_review_api_key')
  . '&results_per_page=300';

$review_entries = get_review_entries($review_query);

$review_seen = array_map('get_review_entry_external_id', $review_entries);

// find any new form entries that need a corresponding entry in the Review API
$to_load = array_diff($form_seen, $review_seen);
if (!isset($to_load)) {
  $to_load = array();
}

// FIXME: hard-coded field names, layout
function render_form_entry($f, $review_entries) {
  $e = has_review_entry($f, $review_entries);
  $output = '<tr>
<td><strong>' . $f['1.3'] . ' ' . $f['1.6'] . '</strong></td>';

  $output .= '<td>';
  if (isset($e['decision'])) {
    $output .= $e['decision']['decision'] . '</strong>';
/*    if (isset($e['decision']['note']) && ($e['decision']['note'] !== '')) {
      $output .= '<br />Note: ' . $e['decision']['note'] . '<br />';
    }*/
  }
  $output .= '</td>';

  $output .= '<td>';
  if (isset($e['reviews']) && (count($e['reviews'] > 0))) {
    foreach ($e['reviews'] as $r) {
      $output .= $r['reviewer'] . ': ' . $r['recommendation'] . ' ';
    }
  }
  $output .= '</td>';

  // email
  $output .= '<td>' . $f['2'] . '</td>
  <td>';
  // affiliation
  if (isset($e['decision'])) {
    $output .= $e['decision']['note'];
  }
  else {
   $output .= $f['4'];
  }
  $output .= '</td></tr>' . "\n";
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
  'reviewKey': '<?php echo get_option('rp_review_api_key'); ?>',
  'registerUrl': '<?php echo get_option('rp_register_url'); ?>'
};
</script>
