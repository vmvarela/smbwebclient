<?php


# Debugging messajes

function Debug ($message, $level = 1) {
  if (isset($_GET['debug'])) {
    $_SESSION['DebugLevel'] = $_GET['debug'];
    unset($_GET['debug']);
  }
  if (! isset($_SESSION['DebugLevel']))
    $_SESSION['DebugLevel'] = 0;
  if ($level <= $_SESSION['DebugLevel']) {
    if (! isset($GLOBALS['DebugHeader'])) {
      GetMimeFile('', 'debug.txt');
      $GLOBALS['DebugHeader'] = 1;
    }
    print $message;
  }
}

?>