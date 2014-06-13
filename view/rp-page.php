<h2>ITP Review</h2>

<?php 
$rp_gravity_query = rp_gravity_query('forms/' . get_option('rp_gravity_form') . '/entries');

$result = NULL;
$ret = http_get($rp_gravity_query);
if ($ret != FALSE) {
  $result = json_decode(http_parse_message($ret)->body, TRUE);
}
if (isset($result)) {
  foreach ($result['response']['entries'] as $g) {
    echo $g['id'] . "\n";
  }
}

?>
