<?php
### Section 3: Main program
#
# Creates a new SmbWebClient object and runs it.
#

set_time_limit(1200);
clearstatcache();
error_reporting(E_ALL ^ E_NOTICE);

session_name('SmbWebClientID');
session_start();
$smb = new SmbWebClient;

header("Cache-control: private");
header("Pragma: public");

$smb->Run();
?>