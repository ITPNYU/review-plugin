<?php
require '../lib/Slim/Slim/Slim.php';

$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );
if (!current_user_can('activate_plugins')) { // indicates an administrator
  exit;
}

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->setName('decision');


$app->post('/decision', function() use ($app) {
  $app->response->headers->set('Content-Type', 'application/json');
  $p = $app->request->post();
  $b = json_decode($app->request->getBody(), TRUE);
  if (isset($b['args']) && isset($b['config'])) {
    $transport = Swift_SmtpTransport::newInstance($b['args']['credentials']['server'], $b['args']['credentials']['port']);
    $transport->setUsername($b['args']['credentials']['username']);
    $transport->setPassword($b['args']['credentials']['password']);
    $transport->setSecure($b['args']['credentials']['transport']);
    $mail = Swift_Message::newInstance();
    $mail->setFrom = array($b['args']['credentials']['username'] => $b['args']['credentials']['username']);
    $mail->setTo(array($b['args']['email'] => $b['args']['fname'] . ' ' . $b['args']['lname']));
    //$mail->addCC($b['args']['credentials']['username']);

    // create decision
    $d_result = NULL;
    $d_body = json_encode(array(
      'decision' => $b['args']['decision'],
      'entry_id' => $b['args']['entry_id'],
      'reviewer' => $b['args']['reviewer']
    ));
    $d_ret = http_post_data($b['config']['reviewUrl'] . '/decision?key=' . $b['config']['reviewKey'],
      $d_body,
      array('headers' => array('Content-Type' => 'application/json'))
    );
    if ($b['args']['decision'] == 'reject') {
      if (isset($b['args']['message']) && isset($b['args']['credentials'])) {
        /*$mail->addAddress($b['args']['email'], $b['args']['fname'] . ' ' . $b['args']['lname']);
        $mail->Subject = $b['args']['subject'];
        $mail->Body = $b['args']['body'];*/
        //$mail->send();
      }
      return;
    }
    if ($d_ret != FALSE) {
      //$app->response->setStatus(201);
      $d_result = json_decode(http_parse_message($d_ret)->body, TRUE);
      //echo(json_encode($d_result));
      if (isset($d_result)) {
        $register_link_code = $b['config']['registerUrl'] . '/?code=' . $d_result['code'];
        if ($b['args']['decision'] == 'comp') {
          $mail->setSubject($b['args']['subject']);
          $mail->setBody($b['args']['body'] . "\n\n" . $register_link_code . "\n");
          $transport->send($mail);
        }
        else if ($d_result['decision'] == 'accept') {
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
          $p_ret = http_get($b['config']['paytrackUrl'] . '/payer?key=' . $b['config']['paytrackKey'] . '&q=' + $filter,
            array('Accept' => 'application/json'));
          if ($p_ret != FALSE) {
            $p_result = json_decode(http_parse_message($p_ret)->body, TRUE);
          }
          $payer_id = NULL;
          if (isset($p_result) && (count($p_result['objects']) == 1)) { // found payer record
            $payer_id = $p_result['objects'][0]['id'];
          }
          else { // create payer record in paytrack
            $p_create_result = NULL;
            $p_body = json_encode(array(
              'fname' => $b['args']['fname'],
              'lname' => $b['args']['lname'],
              'email' => $b['args']['email'],
            ));
            $p_ret = http_post_data($b['config']['paytrackUrl'] . '/payer?key=' . $b['config']['paytrackKey'],
              $p_body,
              array('headers' => array('Content-Type' => 'application/json'))
            );
            if ($p_ret != FALSE) {
              $p_create_result = json_decode(http_parse_message($p_ret)->body, TRUE);
            }
            if (isset($p_create_result)) {
              $payer_id = $p_create_result['id'];
            }
          }
          if (isset($payer_id)) {
            // create invoice
            $i_result = NULL;
            $i_body = json_encode(array(
              'payer_id' => $payer_id,
              'amount' => $b['args']['amount'],
              'account_id' => $b['args']['account_id'],
              'paid' => FALSE,
              'status' => 'sent'
            ));
            $i_ret = http_post_data($b['config']['paytrackUrl'] . '/invoice?key=' . $b['config']['paytrackKey'],
              $i_body,
              array('headers' => array('Content-Type' => 'application/json'))
            ); 
            if ($i_ret != FALSE) {
              $i_result = json_decode(http_parse_message($i_ret)->body, TRUE);
            }

            if (isset($i_result) && isset($i_result['code'])) {
              // associate invoice with decision
              $d_body = json_encode(array(
                'invoice' => $i_result['code']
              ));
              $e_ret = http_put_data($b['config']['reviewUrl'] . '/entry/' . $d_result['entry']['id']
                . '?key=' . $b['config']['reviewKey'],
                $d_body,
                array('headers' => array('Content-Type' => 'application/json'))
              );
              if ($e_ret != FALSE) {
                $decision_i_result = json_decode(http_parse_message($e_ret)->body, TRUE);
                $mail->addAddress($b['args']['email'], $b['args']['fname'] . ' ' . $b['args']['lname']);
                $mail->Subject = $b['args']['subject'];
                $mail->Body = $b['args']['body'];
                //$mail->send();
              }
            }
          }
        }
      }
      else {
        $app->response->setStatus(500);
      }
    }
    else {
      $app->response->setStatus(500);
    }
  }
  else {
    $app->response->setStatus(400);
  }
});

$app->run();

?>
