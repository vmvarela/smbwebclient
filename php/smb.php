<?php
### Section 2.1: Smb class
#
# Samba related methods
#
class Smb {

var $info = array (
  'workgroup' => '',
  'server' => '',
  'share' => '',
  'path' => ''
);

# --------------------------------------------------------------------
# Method: GetTarget
# Description: Set type and name of requested SAMBA object
#
function GetTarget($samba_path) {
  $path = ereg_replace('^/','',cfgSambaRoot.'/'.$samba_path);
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
  Debug("\n$ $cmd\n",2);
  return $this->ParseSmbClient ($cmd, $command, $path);
}

# --------------------------------------------------------------------
# Method: ParseSmbClient
# Description: Parses a smbclient command output
#
function ParseSmbClient ($cmd, $command = '', $path = '') {
  $ocmd = `{$cmd}`;
  Debug($ocmd, 3);
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

}

?>