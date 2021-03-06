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
  . '&results_per_page=600';

$review_entries = get_review_entries($review_query);

$invoice_query = get_option('rp_paytrack_api_url') . '/invoice'
  . '?key=' . get_option('rp_paytrack_api_key')
  . '&results_per_page=600';

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

function get_summary($review_data, $invoices) {
  $summary = array(
    'accept' => 0,
    'comp' => 0,
    'reject' => 0,
    'response_accept' => 0,
    'response_decline' => 0,
    'paid' => 0,
    'revenue' => 0,
    'individual' => array(),
    'accept_breakdown' => array(
      'Current Matriculated ITP or NYU student' => 0,
      'ITP alumni' => 0,
      'NYU Faculty or WE Alumni' => 0,
      'Member of general public' => 0
    ),
    'paid_breakdown' => array(
      'Current Matriculated ITP or NYU student' => array('amount' => 0, 'count' => 0),
      'ITP alumni' => array('amount' => 0, 'count' => 0),
      'NYU Faculty or WE Alumni' => array('amount' => 0, 'count' => 0),
      'Member of general public' => array('amount' => 0, 'count' => 0)
    )
  );
  foreach ($review_data as $r) {
    if (isset($r['decision']['decision'])) {
      $summary[$r['decision']['decision']] += 1;
      if (isset($r['response'])) {
        if ($r['response'] == 'accept') {
          $summary['response_accept'] += 1;
          $u_ext = json_decode($r['external_data'], TRUE);
          $u = get_user_by('login', $u_ext['username']);
          add_user_meta($u->ID, 'rpstatus_we2015', 'confirmed', TRUE); // FIXME: hardcoded year
          $summary['individual'][$r['external_id']] = array('status' => 'accept');
        }
        else if ($r['response'] === 'decline') {
          $summary['response_decline'] += 1;
          $summary['individual'][$r['external_id']] = array('status' => 'decline');
        }
      }
      if ($r['decision']['decision'] === 'accept') {
        $affiliation = $r['affiliation'];
        $a = preg_split('/"/', $r['decision']['note']);
        if (count($a) === 3) {
          $affiliation = $a[1];
        }
        $summary['accept_breakdown'][$affiliation] += 1;
        if ($r['decision']['decision'] === 'accept') {
          $invoice = NULL;
          foreach ($invoices as $i) {
            if ($i['code'] === $r['invoice']) {
              if ($i['paid'] === TRUE) {
                $summary['paid'] += 1;
                $summary['revenue'] += $i['amount'];
                $summary['individual'][$r['external_id']] = array('status' => 'paid $' . $i['amount']);
                $summary['paid_breakdown'][$affiliation]['count'] += 1;
                $summary['paid_breakdown'][$affiliation]['amount'] += $i['amount'];
                $u_ext = json_decode($r['external_data'], TRUE);
                $u = get_user_by('login', $u_ext['username']);
                add_user_meta($u->ID, 'rpstatus_we2015', 'confirmed', TRUE); // FIXME: hardcoded year
              }
            }
          }
        }
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
function render_form_entry($f, $review_entries, $summary) {
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
  if (isset($summary['individual'][$e['external_id']])) {
    $output .= $summary['individual'][$e['external_id']]['status'];
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

$summary = get_summary($review_entries, $invoices);
?>

<div>
  <h4>Summary</h4>
  <dl class="dl-horizontal">
    <dt>Accepted</dt>
    <dd><?php echo $summary['accept']; ?> (<?php echo $summary['paid']; ?> paid)
      <ul>
      <?php foreach (array_keys($summary['accept_breakdown']) as $a) {
        echo '<li>' . $summary['accept_breakdown'][$a] . ' ' . $a . '</li>';
      }
      ?>
      </ul>
    </dd>
    <dt>Comp</dt>
    <dd><?php echo $summary['comp']; ?> (<?php echo $summary['response_accept']; ?> accepted, <?php echo $summary['response_decline']; ?> declined)</dd>
    <dt>Rejected</dt>
    <dd><?php echo $summary['reject']; ?></dd>
    <dt>Confirmed attendees</dt>
    <dd><?php echo ($summary['paid'] + $summary['response_accept']); ?></dd>
    <dt>Payments received</dt>
    <dd>$<?php echo $summary['revenue']; ?>
      <ul>
      <?php foreach (array_keys($summary['paid_breakdown']) as $a) {
        echo '<li>' . $summary['paid_breakdown'][$a]['count']
        . ' ' . $a . ' ($' . $summary['paid_breakdown'][$a]['amount'] . ')</li>';
      }
      ?>
      </ul>
    </dd>
  </dl>
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
  echo render_form_entry($f, $review_entries, $summary);
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
