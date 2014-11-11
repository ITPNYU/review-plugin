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

$invoice_query = get_option('rp_paytrack_api_url') . '/invoice'
  . '?key=' . get_option('rp_paytrack_api_key')
  . '&results_per_page=300';

$invoices = get_invoices($invoice_query);

// find all invoices in the ITP Paytrack API
function get_invoices($invoice_query) {
  $invoices = array();
  $result = NULL;
  $invoice_filters = urlencode(json_encode(array(
    'filters' => array(
      array(
        'name' => 'account_id',
        'op' => 'eq',
        'val' => 3 // FIXME: hardcoded
      )
    )
  )));
  $invoice_query .= '&q=' . $invoice_filters;
  $ret = http_get($invoice_query, array('Accept' => 'application/json'));
  if ($ret != FALSE) {
    $result = json_decode(http_parse_message($ret)->body, TRUE);
  }
  if (isset($result) && isset($result['objects'])) {
    foreach ($result['objects'] as $i) {
      array_push($invoices, $i);
    }
  }
  return $invoices;
}

function get_summary($review_data) {
  $summary = array(
    'accept' => 0,
    'comp' => 0,
    'reject' => 0,
    'response_accept' => 0,
    'response_decline' => 0,
    'paid' => 0,
    'revenue' => 0,
  );
  foreach ($review_data as $r) {
    if (isset($r['decision']['decision'])) {
      $summary[$r['decision']['decision']] += 1;
      if (isset($r['decision']['entry']['response'])) {
        if ($r['decision']['entry']['response'] == 'accept') {
          $summary['response_accept'] += 1;
        }
        else if ($r['decision']['entry']['response'] === 'decline') {
          $summary['response_decline'] += 1;
        }
      }
      if ($r['decision']['decision'] === 'accept') {
        // lookup invoice
        // if paid, increment $summary['paid']
        // if paid, check payment status and add payment amount to $summary['revenue']
      }
    }
  }
  return $summary;
}

function shorten($input) {
  $trunc_input = substr($input, 0, 25);
  if (strlen($input) > 25) {
    $trunc_input .= '...';
  }
  return $trunc_input;
}

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
  // name and email
  $output .= '<td><strong>' . $f['1.3'] . ' ' . $f['1.6'] . '</strong><br />' . $f['2'] . '</td>';

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
      array_push($review_list, '<em>' . $r['reviewer'] . '</em>: <strong>' . $r['recommendation'] . '</strong> - ' . shorten($r['note']));
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

$summary = get_summary($review_entries);
?>

<div>
  <h4>Summary</h4>
  <ul class="list-unstyled">
    <li>Accepted: <?php echo $summary['accept']; ?> (<?php echo $summary['paid']; ?> paid)</li>
    <li>Comp: <?php echo $summary['comp']; ?> (<?php echo $summary['response_accept']; ?> accepted, <?php echo $summary['response_decline']; ?> declined)</li>
    <li>Rejected: <?php echo $summary['reject']; ?></li>
    <li>Total confirmed attendees: <?php echo ($summary['paid'] + $summary['response_accept']); ?></li>
    <li>Total payments received: $<?php echo $summary['revenue']; ?></li>
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
foreach (array_reverse($form_entries, TRUE) as $f) {
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
