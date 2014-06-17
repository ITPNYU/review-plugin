<?php
require '../lib/Slim/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->setName('decision');

$app->post('/decision', function() use ($app) {
  $app->response->headers->set('Content-Type', 'application/json');
  $p = $app->request->post();
  $b = json_decode($app->request->getBody(), TRUE);
  if (isset($b['args']) && isset($b['config'])) {
    // create decision
    $d_result = NULL;
    $d_body = json_encode(array(
      'decision' => $b['args']['decision'],
      'entry_id' => $b['args']['entry_id'],
      'reviewer' => $b['args']['reviewer']
    ));
    $ret = http_post_data($b['config']['reviewUrl'] . '/decision?key=' . $b['config']['reviewKey'],
      $d_body,
      array('headers' => array('Content-Type' => 'application/json'))
    );
    if ($ret != FALSE) {
      $app->response->setStatus(201);
      $d_result = json_decode(http_parse_message($ret)->body, TRUE);
      //echo(json_encode($d_result));
    }
    else {
      $app->response->setStatus(500);
    }
    if (isset($d_result)) {
      // check for existing payer record in paytrack
      $p_result = NULL;
      $filter = urlencode(json_encode(array(
        'filters' => array(
          array(
            'name' => 'email',
            'op' => 'eq',
            'val' => $b['args']['email']
          )
        )
      )));
      $ret = http_get($b['config']['paytrackUrl'] . '/payer?key=' . $b['config']['paytrackKey'] + '&q=' + $filter,
        array('Accept' => 'application/json'));
      if ($ret != FALSE) {
        $p_result = json_decode(http_parse_message($ret)->body, TRUE);
      }
/*      if (isset($p_result) && (count($p_result['objects']) == 1)) { // found payer record
        
      }
      else { // create payer record in paytrack
        $p_create_result = NULL;
        $p_body = json_encode(array(
          'fname' => $b['args']['fname'],
          'lname' => $b['args']['lname'],
          'email' => $b['args']['email'],
        ));
        $ret = http_post_data($b['config']['paytrackUrl'] . '/payer?key=' . $b['config']['paytrackKey'],
          json_encode(),
          array('headers' => array('Content-Type' => 'application/json'))
        );
        if ($ret != FALSE) {
          $p_create_result = json_decode(http_parse_message($ret)->body, TRUE);
        }
      }
*/
    }
  }
  else {
    $app->response->setStatus(400);
  }

});

$app->run();

?>
