<?php
require '../lib/Slim/Slim/Slim.php';

require '../lib/mustache/src/Mustache/Autoloader.php';
Mustache_Autoloader::register();

$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );
if (!current_user_can('activate_plugins')) { // indicates an administrator
  exit;
}

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app->setName('decision');

# Create/update WP user
function rp_create_user ($fname, $lname, $email, $blog) {
  $user_login_prefix = preg_replace('/\W/', '', strtolower(substr($fname, 0, 1) . $lname));
  $user_pass = wp_generate_password(12, FALSE);

  $user_id = email_exists($email);
  if (($user_id == 'admin') || (user_can($user_id, 'activate_plugins'))) {
    // do not allow any changes to administrator accounts
    return;
  }
  if ($user_id) { // user already exists
    $user_info = get_userdata($user_id);
    $user_info->__set('user_pass_clear', $user_pass);
    $user_login = $user_info->user_login;
    wp_update_user(array( 'ID' => $user_id, 'user_pass' => $user_pass));
    add_user_to_blog( $blog, $user_id, "subscriber" ) ;
  }
  else { // user does not exist
    if (username_exists( $user_login )) { // but user name is in use already
      // generate a username with random number suffix
      do {
        $user_login = $user_login_prefix . rand(1, 99);
      } while (username_exists($user_login));
    }
    else {
      $user_login = $user_login_prefix;
    }

    $user_info = array(
      'user_login' => $user_login,
      'user_pass' => $user_pass,
      'user_email' => $email,
      'first_name' => $fname,
      'last_name' => $lname,
      'nickname' => $fname . " " . $lname
    );

    $user_id = wp_insert_user( $user_info );
    $user_info['user_pass_clear'] = $user_pass;
    if (is_wp_error($user_id)) {
      return null;
    }
    else {
      $user_info['wpid'] = $user_id;
      add_user_to_blog( $blog, $user_id, "subscriber" ) ;
      remove_user_from_blog($user_id, 1); // workaround, must manually remove from main blog
    }
  }

  return $user_info;
};

$app->post('/decision', function() use ($app) {
  $app->response->headers->set('Content-Type', 'application/json');
  $p = $app->request->post();
  $b = json_decode($app->request->getBody(), TRUE);
  if (isset($b['args']) && isset($b['config'])) {
    require_once('../lib/swiftmailer/lib/swift_required.php');
    $transport = Swift_SmtpTransport::newInstance(
      $b['args']['credentials']['server'],
      $b['args']['credentials']['port'],
      $b['args']['credentials']['transport']
    );
    $transport->setUsername($b['args']['credentials']['username']);
    $transport->setPassword($b['args']['credentials']['password']);

    $mailer = Swift_Mailer::newInstance($transport);

    $message = Swift_Message::newInstance();
    $message->setFrom(array($b['args']['credentials']['username'] => 'WE Festival')); // FIXME: hardcoded
    $message->setTo(array($b['args']['email'] => $b['args']['fname'] . ' ' . $b['args']['lname']));
    $message->addCc($b['args']['credentials']['username']);

    // create decision
    $d_result = NULL;
    $d_body = json_encode(array(
      'decision' => $b['args']['decision'],
      'entry_id' => $b['args']['entry_id'],
      'reviewer' => $b['args']['reviewer'],
      'note' => $b['args']['note']
    ));
    $d_ret = http_post_data(
      $b['config']['reviewUrl'] . '/decision?key=' . $b['config']['reviewKey'],
      $d_body,
      array('headers' => array('Content-Type' => 'application/json'))
    );
    $user_info = NULL; // will hold username and password for WP user
    if ($b['args']['decision'] == 'reject') {
      if (isset($b['args']['message']) && isset($b['args']['credentials'])) {
        $message->setSubject('WE Festival application status'); // FIXME: hardcoded
        $message->setBody($b['args']['message']['reject'] . "\n\n" . $register_link_code . "\n");
        //$mailer->send($message); // mail disabled
      }
      return;
    }
    else {
      // create/update user if decision is accept/comp
      $user_info = rp_create_user(
        $b['args']['fname'],
        $b['args']['lname'],
        $b['args']['email'],
        6 // FIXME: hardcoded blog ID
      );
      $e_result = NULL;
      if (is_array($user_info)) {
        $user_login = $user_info['user_login'];
        $user_pass_clear = $user_info['user_pass_clear'];
      }
      else {
        $user_login = $user_info->get('user_login');
        $user_pass_clear = $user_info->get('user_pass_clear');
      }
      $e_body = json_encode(array(
        'external_data' => json_encode(array(
          'username' => $user_login,
          'password' => $user_pass_clear
        ))
      ));
      $e_ret = http_put_data(
        $b['config']['reviewUrl'] . '/entry/' . $b['args']['entry_id'] . '?key=' . $b['config']['reviewKey'],
        $e_body,
        array('headers' => array('Content-Type' => 'application/json'))
      );
    }
    if ($d_ret != FALSE) {
      //$app->response->setStatus(201);
      $d_result = json_decode(http_parse_message($d_ret)->body, TRUE);
      //echo(json_encode($d_result));
      if (isset($d_result)) {
        $register_link_code = $b['config']['registerUrl'] . '/?code=' . $d_result['code'];
        if ($b['args']['decision'] == 'comp') {
          $m = new Mustache_Engine;
          $message->setSubject('Register for WE'); // FIXME: hardcoded
          $message->setBody($m->render($b['args']['message']['comp'],
            array('register_link_code' => $register_link_code))
          );
          //$mailer->send($message); // mail disabled
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
                $m = new Mustache_Engine;
                $message->setSubject('Register for WE'); // FIXME: hardcoded
                $message->setBody($m->render($b['args']['message']['accept'],
                  array('register_link_code' => $register_link_code))
                );
                //$mailer->send($message); // mail disabled
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

$app->post('/review', function() use ($app) {
  $app->response->headers->set('Content-Type', 'application/json');
  $p = $app->request->post();
  $b = json_decode($app->request->getBody(), TRUE);
  if (isset($b['args']) && isset($b['config'])) {
    // create review
    $r_result = NULL;
    $r_body = json_encode(array(
      'entry_id' => $b['args']['entry_id'],
      'note' => $b['args']['note'],
      'recommendation' => $b['args']['recommendation'],
      'reviewer' => $b['args']['reviewer']
    ));
    $r_ret = http_post_data($b['config']['reviewUrl'] . '/review?key=' . $b['config']['reviewKey'],
      $r_body,
      array('headers' => array('Content-Type' => 'application/json'))
    );
    if ($r_ret != FALSE) {
      //$app->response->setStatus(201);
      $r_result = json_decode(http_parse_message($r_ret)->body, TRUE);
      //echo(json_encode($r_result));
      if (isset($r_result)) {
        // FIXME: is any further error checking necessary?
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
