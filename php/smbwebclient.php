<?php
### Section 2: SmbWebClient class
#
# All code in a single class
#
class SmbWebClient {

var $info = array (
  'workgroup' => '',
  'server' => '',
  'share' => '',
  'path' => ''
);

var $javascript = '';

function SmbWebClient () {
  $this->style = &$GLOBALS['style'];
  $this->mime_types = &$GLOBALS['mime_types'];
  $this->languages = &$GLOBALS['languages'];
  $this->strings = &$GLOBALS['strings'];
}

# --------------------------------------------------------------------
# Method: Run
# Description: Main function
#   if this is an style file:  dump that file
#   else
#     if a method is requested:  do requested method
#     if we have a error 
#        if we have a logon failure: request new user and password
#        else: show error message
#     else
#       if this is a file: dump that file
#       else: show that directory
#
function Run () {
  if ($this->GetTarget() == 'style-file') {
    $this->StyleFile($_REQUEST['path']);
    exit;
  }

  $this->GetUser();

  if (isset($_REQUEST['method'])) $this->DoMethod($_REQUEST['method']);

  $this->Log();

  switch ($this->Samba('browse')) {
    case '': break;
    case 'NT_STATUS_LOGON_FAILURE': 
      header('Location: '.$this->GetUrl('auth','1'));
      exit;
    default: $this->PrintErrorMessage();
  }

  if ($_SESSION['SmbWebClient_Debug']) exit;

  if ($this->type == 'File') {
    $this->GetMimeFile($this->cachefile, $this->name, isset($_REQUEST['download']));
    # remove file if cache is disabled
    if (cfgCachePath == '') unlink($this->cachefile);
  } else
    $this->ListView();
}

# --------------------------------------------------------------------
# Method: GetUrl
# Description: return an URL (adding a param)
#
function GetUrl ($arg='', $val='') {
  # REQUEST_URI: compatibility with other web servers than Apache
  if (! isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
    if (isset($_SERVER['QUERY_STRING']))
      $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
  }

  if (! isset($_SERVER['HTTPS'])) $_SERVER['HTTPS'] = 'off';
  $url = "http".($_SERVER['HTTPS']=='on'?'s':'')
        ."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

  $url_query = array();
  $parsed_url = parse_url($url);
  if (isset($parsed_url['query'])) parse_str($parsed_url['query'],$url_query);

  # delete switches from URL
  $url_query['O'] = '';
  $url_query['debug'] = '';
  $url_query['lang'] = '';

  if (! is_array($arg)) $url_query[$arg] = $val;
  else foreach ($arg as $key=>$value) $url_query[$key] = $value;

  # build query string
  $query = array();
  foreach ($url_query as $key=>$value) if ($value <> '')
    $query[] = urlencode($key).'='.urlencode($value);
  $parsed_url['query'] = is_array($query) ? join('&',$query) : '';

  # glue URL
  $url = $parsed_url['scheme'].'://';
  if (isset($parsed_url['user'])) {
    $url .= $parsed_url['user'];
    if (isset($parsed_url['pass']))
      $url .= ':'.$parsed_url['pass'];
    $url .= '@';
  }
  $url .= $parsed_url['host'];
  if (isset($parsed_url['port']))  $url .= ':'.$parsed_url['port'];
  if (isset($parsed_url['path']))  $url .= $parsed_url['path'];
  if (isset($parsed_url['query'])) $url .= '?'.$parsed_url['query'];
  if (isset($parsed_url['fragment'])) $url .= '#'.$parsed_url['fragment'];

  return $url;
}

# --------------------------------------------------------------------
# Method: GetTarget
# Description: Set type and name of requested SAMBA object
#   Returns style-file if path is set in "style" array
#
function GetTarget() {
  if (! isset($_REQUEST['path'])) $_REQUEST['path'] = '';
  if (isset($this->style[$_REQUEST['path']])) return 'style-file';
  $path = ereg_replace('^/','',cfgSambaRoot.'/'.$_REQUEST['path']);
  $path = ereg_replace('/$','', $path);
  if ($path == '') {
    $this->type = 'Network';
    $this->winpath = $this->name = 'Windows Network';
  } else {
    $a = split('/',$path);
    for ($i=0, $ap=array(); $i<count($a); $i++) switch ($i) {
      case 0: $this->info['workgroup'] = $a[$i]; break;
      case 1: $this->info['server'] = $a[$i]; break;
      case 2: $this->info['share'] = $a[$i]; break;
      default: $ap[] = $a[$i].'/';
    }
    $this->info['path'] = join('/', $ap);
    switch (count($a)) {
      case 1:  $this->type = 'Workgroup'; break;
      case 2:  $this->type = 'Server';
               $this->winpath = '\\\\'.$this->info['server'];
               break;
      default: $this->type = 'Share';
    }
    if (! isset($this->winpath)) $this->winpath = str_replace('/','\\',$path);
    $this->name = basename($path);
  }
  $this->info['network'] = 'Windows Network';
  $this->path = $path;
}

# --------------------------------------------------------------------
# Method: StyleFile
# Description: Dumps an style file
#
function StyleFile ($file) {
  if (cfgInlineStyle == 'off') {
    if (! is_readable($file)) {
      $f = fopen($file, 'wb');
      fwrite($f, base64_decode($this->style[$file]));
      fclose($f);
    }
    print $this->GetMimeFile ($file, $file);
  } else {
    print $this->GetMimeFile ('', $file);
    $f = base64_decode($this->style[$file]);
    if ($file == 'style.css') {
      $f = str_replace('smbwebclient.php', basename($_SERVER['SCRIPT_NAME']), $f);
    }
    print $f;
  }
}

# --------------------------------------------------------------------
# Method: LoadTemplate
# Description: Loads an HTML template
#
function LoadTemplate ($file, $vars) {
  if (cfgInlineStyle == 'off') {
    if (! is_readable($file)) {
      $f = fopen($file, 'wb');
      fwrite($f, base64_decode($this->style[$file]));
      fclose($f);
    }
    $f = fopen($file, 'r');
    $template = fread ($f, filesize($file));
    fclose ($f);
  } else {
    $template = base64_decode($this->style[$file]);
  }
  return str_replace(array_keys($vars), array_values($vars), $template);
}

# --------------------------------------------------------------------
# Method: GetMimeFile
# Description: Dumps a file with MIME headers
#
function GetMimeFile($file='', $name='', $is_attachment=0) {
  if ($name == '') $name = basename($file);
  $pi = pathinfo(strtolower($name));
  $mime_type = $this->mime_types[$pi['extension']];
  if ($mime_type == '') $mime_type = 'application/octet-stream';
  # dot bug with IE
  if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
    $name = preg_replace('/\./','%2e', $name, substr_count($name, '.') - 1);
  }
  header('MIME-Version: 1.0');
  header("Content-Type: $mime_type; name =\"".htmlentities($name)."\"");
  if ($is_attachment)
    header("Content-Disposition: attachment; filename=\"".htmlentities($name)."\"");
  else
    header("Content-Disposition: filename=\"".htmlentities($name)."\"");
  if ($file <> '' AND is_readable($file)) {
    $fp = fopen($file, "r");
    while (! feof($fp)) {
      print fread($fp,1024*1024);
      flush();
    }
    fclose($fp);
  }
}

# --------------------------------------------------------------------
# Method: _
# Description: Returns a translated string
#
function _($str) {
  if ($this->lang == 'en') return $str;
  $pos = array_search ($str, $this->strings['en']);
  if (($pos = array_search ($str, $this->strings['en'])) === FALSE)
    return $str;
  if ($this->strings[$this->lang][$pos] <> '')
    return $this->strings[$this->lang][$pos];
  if ($this->strings[cfgDefaultLanguage][$pos] <> '')
    return $this->strings[cfgDefaultLanguage][$pos];
  return $str;
}

# --------------------------------------------------------------------
# Method: GetUser
# Description: Load user info (auth, language)
#
function GetUser() {
  # language setup
  if (isset($_GET['lang']) AND isset($this->strings[$_GET['lang']]))
    $_SESSION['SmbWebClient_Lang'] = $_GET['lang'];

  # take a look at HTTP_ACCEPT_LANGUAGE
  if (! isset($_SESSION['SmbWebClient_Lang'])) {
    $accepted_languages = split(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    foreach ($accepted_languages as $lang)
      foreach ($this->languages as $key => $filter)
        if (eregi('^('.$filter.')(;q=[0-9]\\.[0-9])?$', $lang)) {
          $_SESSION['SmbWebClient_Lang'] = $key;
          break 2;
        }  
  }

  # look at HTTP_USER_AGENT
  if (! isset($_SESSION['SmbWebClient_Lang'])) {
    reset($this->languages);
    foreach ($this->languages as $key => $filter)
      if (eregi('(\(|\[|;[[:space:]])(' . $filter . ')(;|\]|\))',
        $_SERVER['HTTP_USER_AGENT'])) {
        $_SESSION['SmbWebClient_Lang'] = $key;
        break;
      }
  }

  # default language
  if (! isset($_SESSION['SmbWebClient_Lang']))
   $_SESSION['SmbWebClient_Lang'] = cfgDefaultLanguage;

  # search preferred language in available languages
  $this->lang = $_SESSION['SmbWebClient_Lang'];
  if (! is_array($this->strings[$this->lang]))
    $this->lang = cfgDefaultLanguage;

  # get user authentication from Share to Network
  foreach (array('Share','Server','Workgroup', 'Network') as $mode) {
    $name = $this->info[strtolower($mode)];
    if (isset($_SESSION['SmbWebClient_Auth'][$mode][$name]))
      $auth = $_SESSION['SmbWebClient_Auth'][$mode][$name];
    else
      $auth = '';
    if (is_array($auth)) {
      $this->login = $auth['login']; 
      $this->password = $auth['password'];
      break;
    }
  }

  if (isset($_POST['login']) AND isset($_POST['password']) AND $_POST['login']<>'' AND $_POST['password']<>'') {

    # authenticating user
    $_SESSION['SmbWebClient_Auth'][$this->type][$this->name] = array (
      'login' => $_POST['login'],
      'password' => $_POST['password']
    );
    $this->login = $_POST['login'];
    $this->password = $_POST['password'];

  } elseif (isset($_GET['auth']) OR (! isset($this->login)) OR $this->login == '' OR $this->password == '') {

    # authentication is needed (form)
    print $this->Page ('', $this->LoadTemplate('style/template-auth.html', array (
      '{action}' => $_SERVER['SCRIPT_NAME'],
      '{input_network_password}' => $this->_('Input the network password'),
      '{cancel}' => $this->_('Cancel'),
      '{resource}' => $this->_($this->winpath),
      '{user}' => $this->_('User'),
      '{password}' => $this->_('Password'),
      '{hidden_path}' => htmlspecialchars($_REQUEST['path']),
      '{login}' => $this->_('Ok')
    )));
    exit;
  }

  # from PHP Samba Explorer (better security)
  putenv('USER='.$this->login.'%'.$this->password);
}

# --------------------------------------------------------------------
# Method: DoMethod
# Description: Calls a given method
#
function DoMethod($method) {
  switch ($method) {
    case 'ListViewAction':   $this->ListViewAction(); break;
    case 'ConfirmOverwrite': $this->ConfirmOverwrite(); break;
  }
}

# --------------------------------------------------------------------
# Method: SmbClient
# Description: Builds a smbclient command
#
function SmbClient ($cmd, $path = '') {
  if ($path <> '') $path = ' -D '.escapeshellarg($path);
  if ($this->info['workgroup'] <> '') $wg = ' -W '.escapeshellarg($this->info['workgroup']);

  # note: -b 1200 do things very fast for me !!!
  return cfgSmbClient.' '.
    escapeshellarg("//{$this->info['server']}/{$this->info['share']}").
    ' '.$path.
    ' '.$wg.
    ' -O '.escapeshellarg(cfgSocketOptions).
    ' -b 1200 -N -c '.escapeshellarg($cmd);
}

# --------------------------------------------------------------------
# Method: Samba
# Description: smbclient interface
#
function Samba ($command, $path='') {
  $this->info['error'] = '';
  switch ($command) {
    case 'browse':
      $this->info['shares'] = 
      $this->info['servers'] = 
      $this->info['workgroups'] = 
      $this->info['files'] = array();
      $server = ($this->info['server'] == '') ? cfgDefaultServer : $this->info['server'];
      if ($this->type <> 'Share') {
        if ($this->type == 'Workgroup') {
          #  who is the master ? (browse network first)
          $this->type = 'Network';
          if (isset($_SESSION['SmbWebClient_Auth']['Network']['Windows Network']))
            $auth = $_SESSION['SmbWebClient_Auth']['Network']['Windows Network'];
          if (isset($this->user)) $oldauth = $this->user.'%'.$this->password;
          else $oldauth = '';
          if (isset($auth['login'])) putenv('USER='.$auth['login'].'%'.$auth['password']);
          $this->Samba('browse');
          $this->info['servers'] = array();
          $this->type = 'Workgroup';
          putenv('USER='.$oldauth);
          $server =
             $this->info['workgroups'][$this->info['workgroup']]['comment'];
          # some networks does not have a master
          if ($server == '') $server = cfgDefaultServer;
          # sometimes no servers are found but master exists
          $this->info['servers'][$server] = array ('type' => 'Server');
        }
        $cmd = cfgSmbClient.' -L '.escapeshellarg($server).' -N';
      } else {
        if ($path == '') $path = $this->info['path'];
        $cmd = $this->SmbClient("dir", $path);
      }
      break;
    case 'spool':
      $this->info['files'] = array();
      $this->type = 'Printer';
      $cmd = $this->SmbClient('queue');
      break;
    case 'get':
      $cmd = $this->SmbClient('dir "'.
        basename($this->info['path']).'"',
        $this->DirName($this->info['path']));
      break;
    case 'get2':
      $this->type = 'File';
      $this->size = $this->info['files'][$this->name]['size'];
      $this->time = $this->info['files'][$this->name]['time'];
      $this->cachefile = (cfgCachePath == '')
        ? tempnam('/tmp','swc')
        : cfgCachePath.'/'.$this->path;
      if (($this->time <> '') AND (cfgCachePath == '' 
        OR (!file_exists($this->cachefile))
        OR filemtime($this->cachefile) < $time)) {
        if (cfgCachePath <> '' AND !is_dir($this->DirName($this->cachefile)))
          $this->MakeDirectory($this->DirName($this->cachefile));
        $path = str_replace('/','\\',$this->info['path']);
        $cmd = $this->SmbClient('get "'.$path.'" "'.$this->cachefile.'"');
      }
      break;
    case 'file_exists':
      $this->info['files'] = array();
      if ($this->info['server'] == '') $server = cfgDefaultServer;
      else $server = $this->info['server'];
      $cmd = $this->SmbClient('dir "'.$path.'"', $this->info['path']);
      break;
    case 'put':
      $cmd = $this->SmbClient('put "'.$_FILES['file']['tmp_name'].'"'.
        ' "'.$_FILES['file']['name'].'"', $this->info['path']);
      break;
    case 'print':
      $cmd = $this->SmbClient('print '.$_FILES['file']['tmp_name']);
      break;
    case 'cancel':
      $cmd = $this->SmbClient('cancel '.$path);
      break;
    case 'delete':
      $cmd = $this->SmbClient('del "'.basename($path).'"', 
        $this->DirName($this->info['path'].'/'.$path));
      break;
    case 'deltree':
      $this->Samba('browse',$path);
      $files = $this->info['files'];
      foreach ($files as $filename => $info)
        $this->Samba('delete', $path.'/'.$filename);
      $cmd = $this->SmbClient('rmdir '.
        escapeshellarg($path), $this->info['path']);
  }
  $this->Debug("\n$ $cmd\n",2);
  return $this->ParseSmbClient ($cmd, $command, $path);
}

# --------------------------------------------------------------------
# Method: ParseSmbClient
# Description: Parses a smbclient command output
#
function ParseSmbClient ($cmd, $command = '', $path = '') {
  $ocmd = `{$cmd}`;
  $this->Debug($ocmd, 3);
  $ipv4 = "([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})";
  foreach (split("\n",$ocmd) as $line) {
    $regs = array();
    if (ereg("^added interface ip={$ipv4} bcast={$ipv4} nmask={$ipv4}$",
      $line,$regs)) {
      $this->info['interface'] = array($regs[1], $regs[2], $regs[3]);
    } else if ($line == "Anonymous login successful") {
      $this->info['anonymous'] = true;
    } else if (ereg("^Domain=\[(.*)\] OS=\[(.*)\] Server=\[(.*)\]$",$line,$regs)) {
      $this->info['description'] = array($regs[1], $regs[2], $regs[3]);
    } else if (ereg("^\tSharename[ ]+Type[ ]+Comment$",$line,$regs)) {
      $mode = 'shares';
    } else if (ereg("^\t---------[ ]+----[ ]+-------$",$line,$regs)) {
      continue;
    } else if (ereg("^\tServer   [ ]+Comment$",$line,$regs)) {
      $mode = 'servers';
    } else if (ereg("^\t---------[ ]+-------$",$line,$regs)) {
      continue;
    } else if (ereg("^\tWorkgroup[ ]+Master$",$line,$regs)) {
      $mode = 'workgroups';
    } else if (ereg("^\t(.*)[ ]+(Disk|IPC)[ ]+IPC.*$",$line,$regs)) {
      continue;
    } else if (ereg("^\tIPC\\\$(.*)[ ]+IPC",$line,$regs)) {
      continue;
    } else if (ereg("^\t(.*)[ ]+(Disk|Printer)[ ]+(.*)$",$line,$regs)) {
      if (trim($regs[1]) <> 'IPC$')
        $this->info['shares'][trim($regs[1])] = array (
          'type'=>$regs[2],
          'comment' => $regs[3]
        );
    } elseif (ereg('([0-9]+) blocks of size ([0-9]+)\. ([0-9]+) blocks available',
      $line, $regs)) {
      $this->info['size'] = $regs[1] * $regs[2];
      $this->info['available'] = $regs[3] * $regs[2];
    } else if (ereg("Got a positive name query response from $ipv4",
      $line,$regs)) {
      $this->info['ip'] = $regs[1];
    } else if (ereg("^session setup failed: (.*)$", $line, $regs)) {
      $this->info['error'] = $regs[1];
    } else if ($line == 'session setup failed: NT_STATUS_LOGON_FAILURE' 
        OR ereg('^tree connect failed: ERRSRV - ERRbadpw', $line)) {
      $this->info['error'] = 'NT_STATUS_LOGON_FAILURE';
    } else if (ereg("^Error returning browse list: (.*)$", $line, $regs)) {
      $this->info['error'] = $regs[1];
    } else if (ereg("^tree connect failed: (.*)$", $line, $regs)) {
      $this->info['error'] = $regs[1];
    } else if (ereg("^Connection to .* failed$", $line, $regs)) {
      $this->info['error'] = 'CONNECTION_FAILED';
    } else if (ereg('^NT_STATUS_INVALID_PARAMETER', $line)) {
      $this->info['error'] = 'NT_STATUS_INVALID_PARAMETER';
    } else if (ereg('^NT_STATUS_DIRECTORY_NOT_EMPTY removing', $line)) {
      $this->info['error'] = 'NT_STATUS_DIRECTORY_NOT_EMPTY';
    } else if (ereg('ERRDOS - ERRbadpath \(Directory invalid.\)', $line)
        OR ereg('NT_STATUS_NOT_A_DIRECTORY', $line)) {
      if ($this->type <> 'File') return $this->Samba('get');
      $this->info['error'] = 'NT_STATUS_NOT_A_DIRECTORY';
    } else if (ereg('^NT_STATUS_NO_SUCH_FILE listing ', $line)) {
      if ($command == 'delete') return $this->Samba('deltree', $path);
      if ($this->type == 'Share' AND $this->info['path'] == '')
        return $this->Samba('spool');
      $this->info['error'] = 'NT_STATUS_NO_SUCH_FILE';
    } else if (ereg('^NT_STATUS_ACCESS_DENIED listing ', $line)) {
      if ($this->type == 'Share' AND $this->info['path'] == '')
        return $this->Samba('spool');
      $this->info['error'] = 'NT_STATUS_ACCESS_DENIED';
    } else if (ereg('^cd (.*): NT_STATUS_OBJECT_PATH_NOT_FOUND$', $line)) {
      if ($this->type <> 'File') return $this->Samba('get');
      $this->info['error'] = 'NT_STATUS_OBJECT_PATH_NOT_FOUND';
    } else if (ereg('^cd (.*): NT_STATUS_OBJECT_NAME_NOT_FOUND$', $line)) {
      $this->info['error'] = 'NT_STATUS_OBJECT_NAME_NOT_FOUND';
    } else if (ereg("^\t(.*)$", $line, $regs)) {
      $this->info[$mode][trim(substr($line,1,21))] = array (
        'type'=>($mode == 'servers') ? 'Server' : 'Workgroup',
        'comment' => trim(substr($line,22))
      );
    } else if ($command == 'spool'
        AND ereg("^([0-9]+)[ ]+([0-9]+)[ ]+(.*)$", $line, $regs)) {
      $this->info['files'][$regs[1].' '.$regs[3]] = array(
        'type'=>'PrintJob',
        'id'=>$regs[1],
        'size'=>$regs[2]
      );
    } else if (ereg('^[ ]+(.*)[ ]+([0-9]+)[ ]+(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[ ]'.
      '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ ]'.
      '+([0-9]+)[ ]+([0-9]{2}:[0-9]{2}:[0-9]{2})[ ]([0-9]{4})$', $line, $regs)) {
      if (ereg("^(.*)[ ]+([D|A|H|S|R]+)$", trim($regs[1]), $regs2)) {
        $attr = trim($regs2[2]);
        $name = trim($regs2[1]);
      } else {
        $attr = '';
        $name = trim($regs[1]);
      }
      if ($name <> '.' AND $name <> '..')
      $this->info['files'][$name] = array (
        'attr' => $attr,
        'size' => $regs[2],
        'time' => $this->ParseTime($regs[4],$regs[5],$regs[7],$regs[6]),
        'type' => (strpos($attr,'D') === false) ? 'File' : 'Directory'
      );
    }
  }
  if ($command == 'get') return $this->Samba('get2');
  return $this->info['error'];
}

# --------------------------------------------------------------------
# Method: FormatBytes
# Description: print KB
#
function FormatBytes ($bytes) {
  if ($bytes < 1024)
    return "1 KB";
  elseif ($bytes < 10*1024*1024)
    return number_format($bytes / 1024,0) . " KB";
  elseif ($bytes < 1024*1024*1024)
    return number_format($bytes / (1024 * 1024),0) . " MB";
  else
    return number_format($bytes / (1024*1024*1024),0) . " GB";
}

# --------------------------------------------------------------------
# Method: ParseTime
# Description: Returns unix time from smbclient output
#
function ParseTime ($m, $d, $y, $hhiiss) {
  $his= split(':', $hhiiss);
  $im = 1 + strpos("JanFebMarAprMayJunJulAgoSepOctNovDec", $m) / 3;
  return mktime($his[0], $his[1], $his[2], $im, $d, $y);
}

# --------------------------------------------------------------------
# Method: Debug
# Description: Debugging messajes
#
function Debug ($message, $level = 1) {
  if (isset($_GET['debug'])) {
    $_SESSION['SmbWebClient_Debug'] = $_GET['debug'];
    unset($_GET['debug']);
  }
  if (! isset($_SESSION['SmbWebClient_Debug']))
    $_SESSION['SmbWebClient_Debug'] = 0;
  if ($level <= $_SESSION['SmbWebClient_Debug']) {
    if (! isset($this->debug_header)) {
      $this->GetMimeFile('', 'debug.txt');
      $this->debug_header = 1;
    }
    print $message;
  }
}

# --------------------------------------------------------------------
# Method: PrintErrorMessage
# Description: Show an error message
#
function PrintErrorMessage () {
  print $this->Page ($this->winpath, $this->LoadTemplate('style/template-error.html', array (
    '{action}' => $_SERVER['SCRIPT_NAME'],
    '{error}' => $this->_('Error'),
    '{ok}' => $this->_('Ok'),
    '{error_message}' => $this->info['error'],
    '{hidden_path}' => htmlspecialchars($this->FromPath('..'))
  )));
  exit;
}

# --------------------------------------------------------------------
# Method: MakeDirectory
# Description: Makes a directory recursively
#
function MakeDirectory ($path, $mode = 0777) {
  if (strlen($path) == 0) return 0;
  if (is_dir($path)) return 1;
  elseif ($this->DirName($path) == $path) return 1;
  return ($this->MakeDirectory($this->DirName($path), $mode)
    and mkdir($path, $mode));
}

# --------------------------------------------------------------------
# Method: Log
# Description: Logging
#
function Log () {
  if (! isset($this->size)) $this->size = 0;
  if (cfgLogFile <> '')
    error_log ("{$_SERVER['REMOTE_ADDR']} - ".
      "{$this->login} [".date('d/M/Y:h:i:s O')."]".
      " \"GET {$this->winpath} HTTP/1.1\" 200 ".intval($this->size).
      " \"{$_SERVER['REQUEST_URI']}\" \"{$_SERVER['HTTP_USER_AGENT']}\"\n",
      3,cfgLogFile);
}


# --------------------------------------------------------------------
# Method: Page
# Description: HTML page
#
function Page ($title, $content) {
  return $this->LoadTemplate('style/template-page.html', array(
    '{stylesheet}' => $this->GetUrl('path','style/style.css'),
    '{lang}' => $this->lang,
    '{title}' => $title,
    '{javascript}' => $this->javascript,
    '{content}' => $content
    ));
}

# --------------------------------------------------------------------
# Method: Link
# Description: HTML a href
#
function Link ($title, $url='') {
  return ($url == '') ? $title : "<a href=\"{$url}\">{$title}</a>";
}

# --------------------------------------------------------------------
# Method: Image
# Description: HTML img
#
function Image ($url, $alt='') {
  return ($url == '') ? $title : "<img src=\"{$url}\" alt=\"{$alt}\" border=\"0\" />";
}

# --------------------------------------------------------------------
# Method: ListView
# Description: print a list view of domains, servers, shared resources,
#   files or printer jobs.
#
function ListView () {
  $items_are_files = FALSE;
  switch ($this->type) {
    case 'Network':   $items = $this->info['workgroups']; break;
    case 'Workgroup': $items = $this->info['servers']; break;
    case 'Server':    $items = $this->info['shares']; break;
    default:          $items = $this->info['files'];
                      $items_are_files = TRUE;
  }

  # create an index
  $index = $this->SortItems($items);

  # print table headers
  $html  = "\n<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n";
  $html .= "<tr>";
  if ($items_are_files) {
    $html .= 
    "<th width=\"20\" align=\"right\">".
    "<input type=\"checkbox\" name=\"chkall\" onclick=\"javascript:sel_all(this)\" />".
    "</th>";
  }
  $html .= "<th width=\"20\" align=\"right\">".
    $this->Image($this->GetUrl('path','style/'.$_SESSION['order'][1].'.jpg'))."</th>";
  $html .= "<th>".$this->Link($this->_("Name"),$this->GetUrl('O','N'))."</th>";
  if (! $items_are_files) {
    $html .= "<th>".$this->Link($this->_("Comments"),$this->GetUrl('O','M'))."</th>";
    $cols = 2;
  } else {
    $html .= "<th align=\"right\">".
      $this->Link($this->_("Size"),$this->GetUrl('O','S'))."</th>";
    $cols = 3;
    if ($this->type <> 'Printer') {
      $html .= "<th>".$this->Link($this->_("Modified"),$this->GetUrl('O','M'))."</th>";
      $html .= "<th>".$this->Link($this->_("Attributes"),$this->GetUrl('O','A'))."</th>";
      $cols = 5;
    }
  }
  $html .= "<th width=\"100%\"><table cellpadding=0 cellspacing=0 border=0 align=\"right\"><tr>";
  $html .= "<td class=\"lang\">".strtoupper($this->lang)."</td>";
  $html .= "<td class=\"logout\">".$this->Link($this->Image($this->GetUrl('path','style/logout.jpg'),$this->_('Logout')),
      $this->GetUrl('auth',1))."</td>";
  $html .= "</tr></table></th>";
  $html .= "</tr>\n";

  # different class to sorted column
  $col_class = array('M'=>'','N'=>'','S'=>'','A'=>'');
  $col_class[$_SESSION['order'][0]] = "class=\"order-by\"";

  # print up link
  if ($_REQUEST['path'] <> '') {
    $html .= "<tr>";
    if ($items_are_files) {
      $html .= "<td width=\"20\" align=\"right\">&nbsp;</td>";
    }
    $html .= "<td class=\"icon\" width=\"20\" align=\"right\">".
      $this->Link($this->Image($this->GetUrl('path','style/dotdot.jpg'),'..'),
      $this->GetUrl('path', $this->FromPath('..')))."</td>";
    $html .= "<td {$col_class['N']}>".$this->Link('..',
      $this->GetUrl('path', $this->FromPath('..')))."</td>";
    if (! $items_are_files) {
      $html .= "<td {$col_class['M']}>&nbsp;</td>";
    } else {
      $html .= "<td {$col_class['S']}>&nbsp;</td>";
      if ($this->type <> 'Printer') {
        $html .= "<td {$col_class['M']}>&nbsp;</td>";
        $html .= "<td {$col_class['A']}>&nbsp;</td>";
      }
    }
    $html .= "<td width=\"100%\">&nbsp</td>";
    $html .= "</tr>\n";
  }

  # print items
  foreach ($index as $file) {
    $html .= "<tr>";
    if ($items_are_files) {
      $value = $this->type == 'Printer' ? $file['info']['id']
        : htmlspecialchars($file['name']);
      $html .= 
      "<td width=\"20\" align=\"right\">".
      "<input type=\"checkbox\" name=\"selected[]\" value=\"$value\" /></td>";
    }
    $img = $this->Image(
        $this->GetUrl('path',strtolower('style/'.$file['info']['type']).'.jpg'), 
        $file['info']['type']);
    # link to download (not view) file
    $img = $this->Link($img, $this->GetUrl(array(
        'path' => $this->FromPath($file['name']),
        'download' => ($file['info']['type'] == 'File') ? 1 : 0
        )));
    $html .= "<td width=\"20\" align=\"right\" class=\"icon\">{$img}</td>";
    $html .= "<td {$col_class['N']}><nobr>".$this->Link($file['name'], 
        $this->type == 'Printer'
            ? '' : $this->GetUrl('path', $this->FromPath($file['name'])))
          ."</td></nobr>";

    if (! $items_are_files) {
      $html .= "<td {$col_class['M']}><nobr>".$file['info']['comment']."</nobr></td>";
    } else {
      $html .= "<td {$col_class['S']} align=\"right\"><nobr>".
        ($file['info']['type'] == 'File' ? $this->FormatBytes($file['info']['size']) : '').
        "</nobr></td>";
      if ($this->type <> 'Printer') {
        $html .= "<td {$col_class['M']}><nobr>".date($this->_("m/d/Y h:i"), 
          $file['info']['time'])."</nobr></td>";
        $html .= "<td {$col_class['A']}>".$file['info']['attr']."</td>";
      }
    }
    $html .= "<td>&nbsp</td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>\n";

  # form to add/delete files
  if ($items_are_files) {
    $html =  $this->LoadTemplate('style/template-listview.html',  array(
      '{action}' => $_SERVER['SCRIPT_NAME'],
      '{hidden_path}' => htmlspecialchars($_REQUEST['path']),
      '{files}' => $html,
      '{delete}' => $this->type == 'Printer'
        ? $this->_('Cancel Selected')
        : $this->_('Delete Selected'),
      '{new}' => $this->type == 'Printer'
        ? $this->_('Print') 
        : $this->_('New File')
    ));
  } 

  # do check all
  $this->javascript =
  "<script language=\"JavaScript\">\n".
  "  function sel_all(master_select) {\n".
  "    with (document.d_form) {\n".
  "      for (i=0; i<elements.length; i++) {\n".
  "        ele = elements[i];\n".
  "      if (ele.type==\"checkbox\")\n".
  "          ele.checked = master_select.checked;\n".
  "      }\n".
  "    }\n".
  "  }\n".
  "</script>\n";

  print $this->Page($this->winpath,"<div id=\"directory\">\n{$html}\n</div>");
}

# --------------------------------------------------------------------
# Method: FromPath
# Description: Builds a new path from current and a relative path
#
function FromPath ($relative='') {
  $path = $_REQUEST['path'];
  switch ($relative) {
    case '.':
    case '':    break;
    case '..':  $path = $this->DirName($path); break;
    default:    $path = ereg_replace('^/', '', $path.'/'.$relative); break;
  }
  return ($path == '.') ? '' : $path;
}

# --------------------------------------------------------------------
# Method: DirName
# Description: I do not like PHP dirname
#
function DirName ($path='') {
  $a = split('/', $path);
  $n = (trim($a[count($a)-1]) == '') ? (count($a)-2) : (count($a)-1);
  for ($dir=array(),$i=0; $i<$n; $i++) $dir[] = $a[$i];
  return join('/',$dir);
}

# --------------------------------------------------------------------
# Method: ListViewAction
# Description: form action
#
function ListViewAction () {
  if ($_POST['do'] == $this->_('New File')) {
    $this->Samba('file_exists', $_FILES['file']['name']);
    if (! isset($this->info['files'][$_FILES['file']['name']])) $this->Samba('put');
    else {
      $file = tempnam('/tmp','SWC');
      copy($_FILES['file']['tmp_name'], $file);
      header("Location: ".$this->GetUrl(array(
        'method' => 'ConfirmOverwrite',
        'file' => basename($file),
        'path' => urlencode($_REQUEST['path']),
        'name' => $_FILES['file']['name']
      )));
      exit;
    }
  } else if ($_POST['do'] == $this->_('Print')) {
    $this->Samba('print');
  } else if ($_POST['do'] == $this->_('Yes, overwrite')) {
    $_FILES['file']['tmp_name'] = '/tmp/'.$_POST['file'];
    $_FILES['file']['name'] = $_POST['name'];
    $this->Samba('put');
  } else if ($_POST['do'] == $this->_('Delete Selected')) {
    if (is_array($_POST['selected']))
      foreach ($_POST['selected'] as $filename)
        $this->Samba('delete', $filename);
  } else if ($_POST['do'] == $this->_('Cancel Selected')) {
    if (is_array($_POST['selected']))
      foreach ($_POST['selected'] as $id) $this->Samba('cancel', $id);
  }
  if (! $_SESSION['SmbWebClient_Debug']) {
    header("Location: ".$this->GetUrl('path', $_REQUEST['path']));
    exit;
  }
}

# --------------------------------------------------------------------
# Method: ConfirmOverwrite
# Description: form action
#
function ConfirmOverwrite () {
  print $this->Page('', $this->LoadTemplate('style/template-confirmoverwrite.html', array(
    '{save_as}' => $this->_('Save as'),
    '{action}' => $_SERVER['SCRIPT_NAME'],
    '{hidden_file}' => htmlspecialchars($_REQUEST['file']),
    '{hidden_name}' => htmlspecialchars($_REQUEST['name']),
    '{hidden_path}' => htmlspecialchars(urldecode($_REQUEST['path'])),
    '{overwrite_question}' => $this->_('Overwrite this file?'),
    '{yes}' => $this->_('Yes, overwrite'),
    '{no}' => $this->_('No, do not overwrite')
    )));
  exit;
}

# --------------------------------------------------------------------
# Method: SortItems
# Description: Makes an index to show files
#
function SortItems ($items) {
  # storing order
  if (! isset($_SESSION['order'])) {
    $_SESSION['order'] = 'AD';
  } elseif (isset($_GET['O'])) {
    if ($_GET['O'] <> $_SESSION['order'][0]) {
      $_SESSION['order'] = $_GET['O'].'A';
    } else {
      if ($_SESSION['order'][1] == 'D')
        $_SESSION['order'] = $_GET['O'].'A';
      else
        $_SESSION['order'] = $_GET['O'].'D';
    }
  }

  # insert objects in order
  $index = array();
  foreach ($items as $name => $info) {
    if (count($index) == 0) {
      $index[] = array ('name' => $name, 'info' => $info);
    } else {
      $index2 = array();
      $inserted = false;
      for ($i = 0; $i < count($index); $i++) {
        if ((! $inserted) AND
        $this->GreaterThan($index[$i]['name'], $index[$i]['info'], $name, $info)) {
          $index2[] = array ('name' => $name, 'info' => $info);
          $inserted = true;
        }
        $index2[] = $index[$i];
      }
      if (! $inserted) $index2[] = array ('name' => $name, 'info' => $info);
      $index = $index2;
    }
  }
  return $index;
}

# --------------------------------------------------------------------
# Method: GreaterThan
# Description: Compares two file records
#
function GreaterThan($name1, $info1, $name2, $info2) {
  switch ($_SESSION['order']) {
    case 'SA':
      return ($info1['size'] > $info2['size'] OR (
        $info1['size'] == $info2['size'] AND
        strtolower($name1) > strtolower($name2)
      ));
    case 'SD':
      return isset($info1['size']) AND ($info1['size'] < $info2['size'] OR (
        $info1['size'] == $info2['size'] AND
        strtolower($name1) < strtolower($name2)
      ));
    case 'MA':
      return (isset($info1['time']) AND $info1['time'] > $info2['time']) OR
         (isset($info1['comment']) AND $info1 ['comment'] > $info2['comment']);
    case 'MD':
      return (isset($info1['time']) AND $info1['time'] < $info2['time']) OR
         (isset($info['comment']) AND $info1 ['comment'] < $info2['comment']);
    case 'AA':
      return $info1['attr'] > $info2['attr'];
    case 'AD':
      return $info1['attr'] < $info2['attr'];
    case 'NA':
      return strtolower($name1) > strtolower($name2);
    case 'ND': 
    default:
      return strtolower($name1) < strtolower($name2);
  }
}

}
?>