<h2>ITP Review</h2>

<?php 
$rp_gravity = json_decode(rp_gravity_query('forms/' . get_option('rp_gravity_form') . '/entries'), TRUE);
var_dump($rp_gravity);

//foreach ($rp_gravity['response']['entries'] as $g) {
//  var_dump($g);
//}

?>
