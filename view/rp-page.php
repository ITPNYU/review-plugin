<h2>ITP Review</h2>

<?php 
$rp_gravity = rp_gravity_query('forms/' . get_option('rp_gravity_form') . '/entries'); 

foreach ($rp_gravity['response']['entries'] as $g) {
  var_dump($g);
}

?>
