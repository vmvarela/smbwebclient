<?php


# Web interface

class SmbWebClient extends Smb {

# Main function:
#   if this is an style file:  dump that file
#   else
#     if a method is requested:  do requested method
#     if we have a error 
#        if we have a logon failure: request new user and password
#        else: show error message
#     else
#       if this is a file: dump that file
#       else: show that directory

function Run () {
  global $style;

  $this->path = @$_REQUEST['path'];

  if (isset($style[$this->path])) {
    $this->StyleFile($this->path);
    exit;
  }

  $this->GetTarget($this->path);
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

  if ($_SESSION['DebugLevel'] > 0) exit;

  if ($this->type == 'File') {
    GetMimeFile($this->cachefile, $this->name, isset($_REQUEST['download']));
    # remove file if cache is disabled
    if (cfgCachePath == '') unlink($this->cachefile);
  } else
    $this->ListView();
}


# Return an URL (adding a param)

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


# Dumps an style file

function StyleFile ($file) {
  global $style;

  if (cfgInlineStyle == 'off') {
    if (! is_readable($file)) {
      $f = fopen($file, 'wb');
      fwrite($f, base64_decode($style[$file]));
      fclose($f);
    }
    GetMimeFile ($file, $file);
  } else {
    GetMimeFile ('', $file);
    $f = base64_decode($style[$file]);
    if ($file == 'style.css') {
      $f = str_replace('smbwebclient.php', basename($_SERVER['SCRIPT_NAME']), $f);
    }
    print $f;
  }
}


# Loads an HTML template

function LoadTemplate ($file, $vars) {
  global $style;

  if (cfgInlineStyle == 'off') {
    if (! is_readable($file)) {
      $f = fopen($file, 'wb');
      fwrite($f, base64_decode($style[$file]));
      fclose($f);
    }
    $f = fopen($file, 'r');
    $template = fread ($f, filesize($file));
    fclose ($f);
  } else {
    $template = base64_decode($style[$file]);
  }
  return str_replace(array_keys($vars), array_values($vars), $template);
}


# Returns a translated string

function _($str) { return GetString($str); }


# Load user info

function GetUser() {

  # auth from session
  $auth = array ('login'=>'', 'password'=>'');
  if (@$_GET['auth'] <> '1')
  foreach (array('Share','Server','Workgroup', 'Network') as $mode) {
    $name = $this->info[strtolower($mode)];
    if (isset($_SESSION['Auth'][$mode][$name])) {
/*
      if (@$_GET['auth'] == '1')
        $_SESSION['Auth'][$mode][$name] = $auth;
      else
*/
        $auth = $_SESSION['Auth'][$mode][$name];
      break;
    }
  }

  # auth from login form
  if (isset($_POST['login']) AND isset($_POST['password'])) {
    $auth = array ('login'=>$_POST['login'], 'password'=>$_POST['password']);
    $_SESSION['Auth'][$this->type][$this->name] = $auth;
  }

  # auth needed (print login form)
  if ($auth['login'] == '' AND (@$_GET['auth'] == '1' OR cfgAnonymous <> 'on')) {

    print $this->Page ('', $this->LoadTemplate('style/template-auth.html', array (
      '{action}' => $_SERVER['SCRIPT_NAME'],
      '{input_network_password}' => $this->_('Input the network password'),
      '{cancel}' => $this->_('Cancel'),
      '{resource}' => $this->_($this->win_path),
      '{user}' => $this->_('User'),
      '{password}' => $this->_('Password'),
      '{hidden_path}' => htmlspecialchars($this->path),
      '{login}' => $this->_('Ok')
    )));
    exit;
  }

  $this->SetUser($auth['login'], $auth['password']);
}


# Calls a given method

function DoMethod($method) {
  switch ($method) {
    case 'ListViewAction':   $this->ListViewAction(); break;
    case 'ConfirmOverwrite': $this->ConfirmOverwrite(); break;
  }
}


# Show an error message

function PrintErrorMessage () {
  print $this->Page ($this->win_path, $this->LoadTemplate('style/template-error.html', array (
    '{action}' => $_SERVER['SCRIPT_NAME'],
    '{error}' => $this->_('Error'),
    '{ok}' => $this->_('Ok'),
    '{error_message}' => $this->info['error'],
    '{hidden_path}' => htmlspecialchars($this->FromPath('..'))
  )));
  exit;
}


# Logging

function Log () {
  if (! isset($this->size)) $this->size = 0;
  if (cfgLogFile <> '')
    @error_log ("{$_SERVER['REMOTE_ADDR']} - ".
      "{$this->login} [".date('d/M/Y:h:i:s O')."]".
      " \"GET {$this->win_path} HTTP/1.1\" 200 ".intval($this->size).
      " \"{$_SERVER['REQUEST_URI']}\" \"{$_SERVER['HTTP_USER_AGENT']}\"\n",
      3,cfgLogFile);
}


# HTML page

function Page ($title, $content) {
  return $this->LoadTemplate('style/template-page.html', array(
    '{stylesheet}' => $this->GetUrl('path','style/style.css'),
    '{lang}' => $_SESSION['Language'],
    '{title}' => $title,
    '{content}' => $content
    ));
}


# HTML a href

function Link ($title, $url='', $name='') {
  if ($name <> '') $name = "name = \"{$name}\"";
  return ($url == '') ? $title : "<a href=\"{$url}\" {$name}>{$title}</a>";
}


# HTML img

function Image ($url, $alt='') {
  return ($url == '') ? $title : "<img src=\"{$url}\" alt=\"{$alt}\" border=\"0\" />";
}


# Print a list view of domains, servers, shared resources,
# files or printer jobs.

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
  $html .= "<td class=\"lang\">".strtoupper($_SESSION['Language'])."</td>";
  $html .= "<td class=\"logout\">".$this->Link($this->Image($this->GetUrl('path','style/logout.jpg'),$this->_('Logout')),
      $this->GetUrl('auth',1))."</td>";
  $html .= "<td class=\"time\">".date('h:i')."</td>";
  $html .= "</tr></table></th>";
  $html .= "</tr>\n";

  # different class to sorted column
  $col_class = array('M'=>'','N'=>'','S'=>'','A'=>'');
  $col_class[$_SESSION['order'][0]] = "class=\"order-by\"";

  # print up link
  if ($this->path <> '') {
    $html .= "<tr>";
    if ($items_are_files) {
      $html .= "<td width=\"20\" align=\"right\">&nbsp;</td>";
    }
    $html .= "<td class=\"icon\" width=\"20\" align=\"right\">".
      $this->Link($this->Image($this->GetUrl('path','style/dotdot.jpg'),'..'),
      $this->GetUrl('path', $this->FromPath('..'))."#".basename($this->path))."</td>";
    $html .= "<td {$col_class['N']}>".$this->Link('..',
      $this->GetUrl('path', $this->FromPath('..'))."#".basename($this->path))."</td>";
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
            ? '' : $this->GetUrl('path', $this->FromPath($file['name'])), $file['name'])
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
      '{hidden_path}' => htmlspecialchars($this->path),
      '{files}' => $html,
      '{delete}' => $this->type == 'Printer'
        ? $this->_('Cancel Selected')
        : $this->_('Delete Selected'),
      '{new}' => $this->type == 'Printer'
        ? $this->_('Print') 
        : $this->_('New File')
    ));
  } 

  print $this->Page($this->win_path,"<div id=\"directory\">\n{$html}\n</div>");
}


# Form action

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
        'path' => urlencode($this->path),
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
  if (! $_SESSION['DebugLevel']) {
    header("Location: ".$this->GetUrl('path', $this->path));
    exit;
  }
}


# Form action

function ConfirmOverwrite () {
  print $this->Page('', $this->LoadTemplate('style/template-confirmoverwrite.html', array(
    '{save_as}' => $this->_('Save as'),
    '{action}' => $_SERVER['SCRIPT_NAME'],
    '{hidden_file}' => htmlspecialchars($_REQUEST['file']),
    '{hidden_name}' => htmlspecialchars($_REQUEST['name']),
    '{hidden_path}' => htmlspecialchars(urldecode($this->path)),
    '{overwrite_question}' => $this->_('Overwrite this file?'),
    '{yes}' => $this->_('Yes, overwrite'),
    '{no}' => $this->_('No, do not overwrite')
    )));
  exit;
}


# Makes an index to show files

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


# Compares two file records

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