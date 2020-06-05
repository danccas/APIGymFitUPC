<?php
require_once(__DIR__ . '/app/librarys/route.php');

Route::import('conf.php');

Route::init()->debug(true);

Route::g()->libs->sesion->init();

$db = Route::g()->libs->doris->moviles;

Route::any('password_reset', function() {
  Route::view('theme_recover');
});
Route::path('api', function() use($db) {
  Route::get('account/check_if_account_exists', function() use($db) {
    $email = Route::input('email');

    $user = $db->get('SELECT * FROM user WHERE email = :email', true, array(
      'email' => $email,
    ));
    if(!empty($user)) {
      Route::responseJSON(200, array(
        'response' => $user['email'],
      ));
    } else {
      Route::responseJSON(200, array(
        'response' => 'Error',
      ));
    }
  });
  Route::post('account/login', function() use($db) {
    $username = Route::input('username');
    $password = Route::input('password');
    
    $username = $db->get('SELECT * FROM user WHERE (username = :username OR email = :username) AND password = :password', true, array(
      'username' => $username,
      'password'   => $password,
    ));
    if(!empty($username)) {
      $token = md5(time());
      $db->insert('token', array(
        'user_id' => $username['id'],
        'token'      => $token,
      ));
      Route::responseJSON(200, array(
        'response' => 'Successfully authenticated',
        'pk'       => 892,
        'email'    => $username['email'],
        'token'    => $token,
      ));
    } else {
      Route::responseJSON(200, array(
        'response' => 'Error',
        'error_message'  => 'Invalid credentials',
      ));
    }
  });
  Route::post('account/register', function() use($db) {
    Route::library('formity2', 'Formity');
    $form = Formity::g('registro');
    $form->obfuscate = false;
    $form->addField('email', 'input:email');
    $form->addField('username', 'input:text');
    $form->addField('password', 'input:text');
    $form->addField('password2', 'input:text');
    if($form->byRequest())  {
      if(!$form->isValid($err)) {
        Route::responseJSON(200, array(
          'response' => 'Error',
          'error_message' => $err,
        ));
      } else {
        $data = $form->getData();
        if($data['password'] != $data['password2']) {
          Route::responseJSON(200, array(
            'response' => 'Error',
            'error_message'  => 'password invalid',
          ));
        } else {
          $user = $db->get('SELECT * FROM user WHERE email = :email OR username = :username', true, array(
            'email'   => $data['email'],
            'username' => $data['username'],
          ));
          if(!empty($user)) {
            Route::responseJSON(200, array(
              'response' => 'Error',
              'error_message' => 'That email is already in use.',
            ));
          } else {
            unset($data['password2']);
            $db->transaction();
            $user_id = $db->insert('user', $data);
            $token = md5(time());
            $db->insert('token', array(
              'user_id' => $user_id,
              'token'   => $token,
            ));
            $db->commit();
            Route::responseJSON(200, array(
              'response' => 'successfully registered new user.',
              'email'    => $data['email'],
              'username' => $data['username'],
              'pk'       => $user_id,
              'token'    => $token, 
            ));
          }
        }
      }
    }
  });
  function valid_token($db) {
    $auth  = Route::header('Authorization');
    $token = explode(' ', $auth);
    $token = $token[1];
    if(empty($token)) {
      Route::response(404);
    }
    $token = $db->get("
      SELECT T.id, U.email, U.username
      FROM token T
        JOIN user U ON U.id = T.user_id
      WHERE T.token = :token", true, array(
      'token' => $token,
    ));
    if(empty($token)) {
      Route::responseJSON(401, array(
        'detail' => 'Invalid token.',
      ));
    }
    return $token;
  }
  function upload_image($path, $imagen, &$error = null) {
    $out_name = time() . uniqid() . '.jpg';
    if(move_uploaded_file($imagen['tmp_name'], $path . $out_name)) {
      return $out_name;
    }
    $error = 'No se ha podido subir la imagen';
    return false;
  }
  Route::get('account/properties', function() use($db) {
    $token = valid_token($db);
    Route::responseJSON(200, array(
      'pk'    => $token['id'],
      'email' =>  $token['email'],
      'username' => $token['username'],
    ));
  });
  Route::get('blog/list', function() use($db) {
    $token = valid_token($db);
    $ls = $db->get('SELECT * FROM proveedor');
    $ls = array_map(function($n) {
      return array(
        'pk' => $n['id'],
        'title' => $n['nombre'],
        'slug'  => $n['slug'],
        'body'  => $n['texto'],
        'image' => 'http://apigymfit.anccas.org/storage/' . $n['imagen'],
        'date_updated' =>  date("Y-m-d\TH:i:s.000\Z", strtotime("2013-05-07 18:56:57")),
        'username' => 'rick',
      );
    }, $ls);
    Route::responseJSON(200, array(
      'count'    => count($ls),
      'next'     => 'http://apigymfit.anccas.org/api/blog/list',
      'previous' => null,
      'results'  => $ls,
    ));
  });
  Route::get('blog/:slug/is_author', array(
    'slug' => '[\w\-]+',
  ),function() use($db) {
    $token = valid_token($db);
    if(rand(1,10) < 5) {
      Route::responseJSON(200, array(
        'response' => 'You don\'t have permission to edit that.',
      ));
    } else {
      Route::responseJSON(200, array(
        'response' => 'You have permission to edit that.',
      ));
    }
  });
  Route::post('blog/create', function() use($db) {
    $token = valid_token($db);
    Route::library('formity2', 'Formity');
    $form = Formity::g('registro');
    $form->obfuscate = false;
    $form->addField('title', 'input:text');
    $form->addField('body', 'textarea');
    $form->addField('image', 'input:file');

    if($form->byRequest())  {
      if(!$form->isValid($err)) {
        Route::responseJSON(200, array(
          'response' => 'Error',
          'error_message' => $err,
        ));
      } else {
        $data = $form->getData();
        $path = __DIR__ . '/storage/';
        if(!($img = upload_image($path, $data['image'], $error))) {
          Route::responseJSON(200, array(
            'response' => 'Error',
            'error_message' => $error,
          ));
        } else {
          $data['image'] = $img;
          $pk = $db->insert('proveedor', array(
            'empresa_id' => 1,
            'nombre' => $data['title'],
            'slug'   => generar_slug($data['title']),
            'texto'  => $data['body'],
            'imagen' => $data['image'],
          ));
          Route::responseJSON(200, array(
            'response' => 'created',
            'pk' => $pk,
            'title' => $data['title'],
            'slug'  => generar_slug($data['title']),
            'body'  => $data['body'],
            'image' => 'http://apigymfit.anccas.org/storage/' . $img,
            'date_updated' => date("Y-m-d\TH:i:s.000\Z", strtotime("2013-05-07 18:56:57")),
            'username' => 'rick',
          ));
        }
      }
    }
  });
  Route::get('blog/:slug/update', array(
    'slug' => '[\w\-]+',
  ),function() use($db) {
    $token = valid_token($db);
  });
});
Route::else(function() {
  Route::response(404);
});
