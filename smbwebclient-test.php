<?php

define ('cfgInlineStyle', 'off');
define ('cfgLogFile', 'smbwebclient.log');

error_reporting(E_ALL);

include 'php/includes.php';

foreach ($includes as $archivo) if ($archivo <> '')
  include ("php/".$archivo.".php");

?>