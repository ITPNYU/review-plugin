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

$review_query = get_option('rp_review_api_url') . '/entry'
  . '?key=' . get_option('rp_review_api_key')
  . '&results_per_page=300';

$review_entries = get_review_entries($review_query);

// FIXME: hard-coded field names, layout
function render_form_entry($f, $review_entries) {
  $e = has_review_entry($f, $review_entries);
  $output = '<tr ';
  if (isset($e['decision'])) {
    if (($e['decision']['decision'] === 'accept') || ($e['decision']['decision'] === 'comp')) {
      $output .= 'class="success"';
    }
    else if ($e['decision']['decision'] === 'reject') {
      $output .= 'class="danger"';
    }
  }
  $output .= '>'; // closing tr tag
  $output .= '<td>' . $f['id'] . '</td>';
  $output .= '<td><strong>' . $f['1.3'] . ' ' . $f['1.6'] . '<br />' . $f['2'] . '</strong></td>';

  $output .= '<td>';
  if (isset($e['decision'])) {
    $output .= $e['decision']['decision'] . '</strong>';
  }
  $output .= '</td>';

  $output .= '<td>';
  if (isset($e['decision'])) {
    if (isset($e['decision']['entry']['response'])) {
      $output .= $e['decision']['entry']['response'];
    };
  }
  $output .= '</td>';


  $output .= '<td>';
  if (isset($e['reviews']) && (count($e['reviews'] > 0))) {
    $review_list = array();
    foreach ($e['reviews'] as $r) {
      array_push($review_list, $r['reviewer'] . ': ' . $r['recommendation'] . substr($r['note'], 15));
    }
    $output .= implode('<br />', $review_list);
  }
  $output .= '</td>';

  $output .= '<td>';
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

<div>
  <h4>Summary</h4>
  <ul class="list-unstyled">
    <li>Accepted: N (N paid)</li>
    <li>Comp: N (N accepted, N declined)</li>
    <li>Rejected: N</li>
    <li>Total confirmed attendees: N</li>
    <li>Total payments received: $N</li>
  </ul>
</div>

<table class="table table-striped">
  <thead>
    <tr>
      <th>#</th>
      <th>Applicant</th>
      <th>Decision</th>
      <th>Paid/Accepted</th>
      <th>Reviews</th>
      <th>Affiliation</th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($form_entries as $f) {
  echo render_form_entry($f, $review_entries);
}

?>
  </tbody>
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
