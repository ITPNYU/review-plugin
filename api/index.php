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
    $ret = http_post_data($b['config']['reviewUrl'] + '/decision?key=' + $b['config']['reviewKey'],
      json_encode($b['args']),
      array('headers' => array('Content-Type' => 'application/json'))
    );
    if ($ret != FALSE) {
      $app->response->setStatus(201);
      $d_result = json_decode(http_parse_message($ret)->body, TRUE);
      echo(json_encode($d_result));
    }
    else {
      $app->response->setStatus(500);
    }
/*    if (isset($d_result)) {
      // check for existing payer record in paytrack
      $p_result = NULL;
      $filter = urlencode(json_encode(array(
        'filters' => array(
          array(
            'name' => 'email',
            'op' => 'eq',
            'val' => $d_result['entry']['email']
          )
        )
      )));
      $ret = http_get_data($b['config']['paytrackUrl'] + '/payer?key=' + $b['config']['paytrackKey']
        + '&q=' + $filter);
      if ($ret != FALSE) {
        $p_result = json_decode(http_parse_message($ret)->body, TRUE);
      }
      if (isset($p_result) && (count($p_result['objects']) == 1)) { // found payer record
        
      }
      else { // create payer record in paytrack
        $p_create_result = NULL;
        $ret = http_post_data($b['config']['paytrackUrl'] + '/payer?key=' + $b['config']['paytrackKey'],
          json_encode(),
          array('headers' => array('Content-Type' => 'application/json'))
        );
        if ($ret != FALSE) {
          $p_create_result = json_decode(http_parse_message($ret)->body, TRUE);
        }
      }
    }*/
  }
  else {
    $app->response->setStatus(400);
  }

});

$app->run();

?>
