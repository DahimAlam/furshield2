<?php
function user()        { return $_SESSION['user'] ?? null; }
function logged_in()   { return isset($_SESSION['user']); }
function has_role($r)  { return logged_in() && (($_SESSION['user']['role'] ?? '') === $r); }

function require_login(){
  if (!logged_in()) { header("Location: ".BASE."/login.php"); exit; }
}

function require_role($role){
  require_login();
  if (!has_role($role)) { http_response_code(403); echo "Forbidden"; exit; }
}
