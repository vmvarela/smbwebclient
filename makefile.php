<?php

include "php/languages.php";

class SmbWebClientBuild {

# --------------------------------------------------------------------
# Method: ListTranslators
# Description: List the people that translates SMB Web Client
#
function ListTranslators() {
  global $languages;
  print "<?php\n";
  print "#  Translators:\n";
  $lines = file("http://www.nivel0.net/SmbWebClientTranslationData/export.xml");
  $lang = join('|',array_keys($languages));
  foreach ($lines as $line) {
    if (ereg('^\[([0-9]{3})\](.*)$', $line, $regs) OR
        ereg('^\[(.*)\]\[([0-9]{3})\](.*)$', $line, $regs)) {
      continue;
    } elseif (ereg('^\[('.$lang.'})\](.*)$', $line, $regs)) {
      print "#   [{$regs[1]}] {$regs[2]}";
    }
  }
  print "\n?>";
}

# --------------------------------------------------------------------
# Method: MakeMimeTypesArray
# Description: Builds PHP code for $this->mime_types array
#
function MakeMimeTypesArray ($mt_f) {
  $this->mime_types = array();
  if ($mt_f == '') $mt_f = '/etc/mime.types';
  if (! is_readable($mt_f)) return;
  $mt_fd=fopen($mt_f,"r");
  while (!feof($mt_fd)) {
    $mt_buf=trim(fgets($mt_fd,1024));
    if ((strlen($mt_buf) > 0) AND (substr($mt_buf,0,1) != "#")) {
      $mt_tmp=preg_split("/[\s]+/", $mt_buf, -1, PREG_SPLIT_NO_EMPTY);
      for ($i=1,$mt_num=count($mt_tmp);$i<$mt_num AND !strstr($mt_tmp[$i],"#");$i++)
        $this->mime_types[$mt_tmp[$i]]=$mt_tmp[0];
      unset($mt_tmp);
    }
  }
  fclose($mt_fd);
  print "var \$mime_types = array (\n";
  foreach ($this->mime_types as $ext => $mime_type) {
    print "'{$ext}'=>'{$mime_type}', ";
    if (($i++ % 3) == 0) print "\n  ";
  }
  print ");\n";
}


# --------------------------------------------------------------------
# Method: MakeStyleArray
# Description: Builds PHP code for $this->style array
#
function MakeStyleArray() {
  print "<?php\n";
  $archivos = explode("\n",str_replace(" ", "", `ls style`));
?>
#
# Theme files
# Files are included using base64_encode PHP function
#
<?php
  print "\$style = array (\n";
  foreach ($archivos as $archivo) if ($archivo <> '') {
    if (! is_readable("style/{$archivo}")) $encoded = "**error**";
    else {
      $f = fopen("style/{$archivo}", "r");
      $encoded = base64_encode(fread($f, filesize("style/{$archivo}")));
      fclose($f);
    }
    print "  'style/{$archivo}' => '{$encoded}',\n";
  }
  print ");\n\n?>";
}

# --------------------------------------------------------------------
# Method: MakeStringsArray
# Description: Builds PHP code for $this->strings array
#
function MakeStringsArray() {
  print "<?php\n";
?>
#
# Available translations at
# http://wwww.nivel0.net/SmbWebClientTranslation
#
<?php
  $lines = file("http://www.nivel0.net/SmbWebClientTranslationData/export.xml");
  foreach ($lines as $line) {
    if (ereg('^\[([0-9]{3})\](.*)$', $line, $regs)) {
      $original[intval($regs[1])] = $regs[2];
    } elseif (ereg('^\[(.*)\]\[([0-9]{3})\](.*)$', $line, $regs)) {
      $translation[$regs[1]][intval($regs[2])] = $regs[3];
    }
  }
  $translation['en'] = $original;
  print '$strings = array ('."\n";
  foreach ($translation as $lang => $s) {
    print "  '{$lang}' => array(";
    for ($i = 1, $a=array(); $i <= count($original); $i++) {
      $a[] = "'".trim(addslashes($s[$i]))."'";
    }
    print join(',',$a)."),\n";
  }
  print ");\n\n?>";
}

# --------------------------------------------------------------------
# Method: MakeSmbWebClient
# Description: Builds SmbWebClient script
#
function MakeSmbWebClient () {
    include 'php/includes.php';
    foreach ($includes as $archivo) if ($archivo <> '') {
      $a = file("php/".$archivo.".php");
      for ($i = 1; $i < count($a)-1; $i++) {
        $smbwebclient[] = str_replace('@@@', '$', $a[$i]);
      }
    }
    print "<?php\n";
    print implode("", $smbwebclient);
    print "?>";
}

}

set_time_limit(1200);
clearstatcache();

$smb = new SmbWebClientBuild;

switch ($argv[1]) {
  case 'mime_types':  $smb->MakeMimeTypesArray($argv[2]); break; 

  case 'translators':         $smb->ListTranslators(); break;
  case 'style':               $smb->MakeStyleArray(); break;
  case 'strings':             $smb->MakeStringsArray($argv[2]); break;
  case 'smbwebclient.php':    $smb->MakeSmbWebClient(); break;
  default:
    print "\nusage: php4 -f build.php [smbwebclient.php | strings | style | translators]\n";
}

?>