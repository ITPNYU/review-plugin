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

function rp_render_editor($e, $f) {
  $affiliation_amount = array(
    'Current Matriculated ITP or NYU student' => 50,
    'Current Matriculated non-NYU student' => 75,
    'ITP alumni' => 125,
    'NYU Faculty or WE Alumni' => 200,
    'Member of general public' => 350
  );
  $output ='<div class="rp-editor-controls">
<ul class="list-unstyled">
<li>Set affiliation: <select class="rp-affiliation-select" id="rp-entry-' . $e['id'] . '-affiliation" data-rp-entry="' . $e['id'] . '">';
  foreach (array_keys($affiliation_amount) as $k) {
    $selected = '';
    if ($k === $f[4]) {
      $selected = 'selected';
    }
    $output .= '<option value="' . $k . '" ' . $selected . '>' . $k . '</option>';
  }
  $output .= '</select></li>
<li>Set invoice amount: <input class="rp-amount-input" id="rp-entry-' . $e['id'] . '-amount" data-rp-entry="' . $e['id'] . '" type="text" value="' . $affiliation_amount[$f[4]] . '"></li>
</ul>
</div><!-- .rp-editor-controls -->';
  return $output;
}

// FIXME: hard-coded field names, layout
function render_form_entry($f, $review_entries) {
  $e = has_review_entry($f, $review_entries);
  $output = '<tr>
<td><strong>' . $f['id'] . ': ' . $f['1.3'] . ' ' . $f['1.6'] . '</strong></td>
<td><div id="rp-entry-' . $e['id'] . '"'
  . ' data-rp-entry_id="' . $e['id'] . '"'
  . ' data-rp-entry-fname="' . $f['1.3'] . '"'
  . ' data-rp-entry-lname="' . $f['1.6'] . '"'
  . ' data-rp-entry-email="' . $f['2'] . '"'
  . '>';

  if (isset($e['reviews']) && (count($e['reviews'] > 0))) {
    foreach ($e['reviews'] as $r) {
      $output .= '<div>
<em>Review from ' . $r['reviewer'] . '</em>: <b>' . $r['recommendation'] . '</b> - ' . $r['note'] .

'</div>';
    }
  }
  if (isset($e['decision'])) {
    $output .= '<strong>Decision: ' . $e['decision']['decision'] . '</strong>';
  }
  else {
    $output .= rp_render_editor($e, $f);
    $output .= '<div class="rp-decision-buttons">
<button type="button" data-rp-action="accept" data-rp-entry="' . $e['id'] . '" class="btn btn-success rp-decision-button">Accept</button>
<button type="button" data-rp-action="reject" data-rp-entry="' . $e['id'] . '" class="btn btn-danger rp-decision-button">Reject</button>
<button type="button" data-rp-action="comp" data-rp-entry="' . $e['id'] . '" class="btn btn-info rp-decision-button">Comp</button>
</div>';
  }

  $output .= '<br /><br /><ul class="list-unstyled">
  <li><strong>Email</strong>: ' . $f['2'] . '</li>
  <li><strong>Website</strong>: ' . $f['3'] . '</li>
  <li><strong>Affiliation</strong>: ' . $f['4'] . '</li>
  <li><strong>School</strong>: ' . $f['5'] . '</li>
  <li><strong>How did you hear about WE?</strong>: ' . $f['6'] . '</li>
  <li><strong>What are you doing now?</strong>: ' . rp_parse_opt($f, '7') . '</li>
  <li><strong>Other?</strong>: ' . $f['8'] . '</li>
  <li><strong>What stage are you in?</strong>: ' . rp_parse_opt($f, '9') . '</li>
  <li><strong>Other?</strong>: ' . $f['10'] . '</li>
  <li><strong>How will WE help you?</strong>: ' . $f['11'] . '</li>
  <li><strong>What do you bring to WE?</strong>: ' . $f['12'] . '</li>
  <li><strong>Elevator pitch</strong>: ' . $f['13'] . '</li>
  <li><strong>Morning Panel</strong>: ' . rp_parse_opt($f, '14') . '</li>
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
  'reviewKey': '<?php echo get_option('rp_review_api_key'); ?>',
  'registerUrl': '<?php echo get_option('rp_register_url'); ?>'
};

var rpAffiliationSelect = function(args) {
  console.log('affiliation change for entry ' + args['entry']);
  var affiliationAmount = {
    'Current Matriculated ITP or NYU student': 50,
    'Current Matriculated non-NYU student': 75,
    'ITP alumni': 125,
    'NYU Faculty or WE Alumni': 200,
    'Member of general public': 350
  };
  jQuery('input#rp-entry-' + args['entry'] + '-amount').val(affiliationAmount[args['affiliation']]);
};

var rpDecisionButton = function(args) {
  console.log('decision click ' + args['action'] + ' ' + args['entry']);
  // make a note of the affiliation and amount set at decision time
  var note = 'affiliation: "' + jQuery('select#rp-entry-' + args['entry'] + '-affiliation').val() + '", '
    + 'amount: $' + jQuery('input#rp-entry-' + args['entry'] + '-amount').val();
  jQuery.ajax({
    url: '<?php echo network_site_url() . 'wp-content/plugins/review-plugin/api/decision'; ?>',
    data: JSON.stringify({
      'args': {
        'entry_id': args['entry'],
        'decision': args['action'],
        'reviewer': '<?php global $current_user; get_currentuserinfo(); echo $current_user->user_login; ?>',
        'fname': jQuery('div#rp-entry-' + args['entry']).attr('data-rp-entry-fname'),
        'lname': jQuery('div#rp-entry-' + args['entry']).attr('data-rp-entry-lname'),
        'email': jQuery('div#rp-entry-' + args['entry']).attr('data-rp-entry-email'),
        'account_id': 3,
        'amount': jQuery('input#rp-entry-' + args['entry'] + '-amount').val(),
        'note': note,
        'message': {
          'accept': <?php echo json_encode(get_option('rp_message_accept')); ?>,
          'reject': <?php echo json_encode(get_option('rp_message_reject')); ?>,
          'comp': <?php echo json_encode(get_option('rp_message_comp')); ?>,
        },
        'credentials': {
          'server': <?php echo json_encode(get_option('rp_message_server')); ?>,
          'port': <?php echo json_encode(get_option('rp_message_port')); ?>,
          'transport': <?php echo json_encode(get_option('rp_message_transport')); ?>,
          'username': <?php echo json_encode(get_option('rp_message_username')); ?>,
          'password': <?php echo json_encode(get_option('rp_message_password')); ?>
        }
      },
      'config': config
    }),
    dataType: 'json',
    type: 'POST',
    contentType: 'application/json',
    success: function(data) {
      jQuery('div#rp-entry-' + args['entry'] + ' > div.rp-decision-buttons')
      .html('<b>Decision:</b> ' + args['action'] + '<br />Note: ' + note);
    },
    error: function(xhr, status, errorThrown) {
      alert('There was an error saving this decision: ' + errorThrown);
    }
  });
};

jQuery(document).ready(function() {
  jQuery('button.rp-decision-button').on('click', function() {
    rpDecisionButton({
      'action': jQuery(this).attr('data-rp-action'),
      'entry': jQuery(this).attr('data-rp-entry')
    });
  });

  jQuery('select.rp-affiliation-select').on('change', function() {
    rpAffiliationSelect({
      'entry': jQuery(this).attr('data-rp-entry'),
      'affiliation': jQuery(this).val()
    });
  })
});

</script>
