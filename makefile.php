<?php

$languages = array(
  'af' => 'Afrikaans',
  'ar' => 'Arabic',
  'az' => 'Azerbaijani',
  'bg' => 'Bulgarian',
  'bs' => 'Bosnian',
  'ca' => 'Catalan',
  'cs' => 'Czech',
  'da' => 'Danish',
  'de' => 'German',
  'el' => 'Greek',
  'en' => 'English',
  'eo' => 'Esperanto',
  'es' => 'Spanish',
  'et' => 'Estonian',
  'eu' => 'Basque',
  'fa' => 'Persian',
  'fi' => 'Finnish',
  'fr' => 'French',
  'gl' => 'Galician',
  'he' => 'Hebrew',
  'hi' => 'Hindi',
  'hr' => 'Croatian',
  'hu' => 'Hungarian',
  'id' => 'Indonesian',
  'it' => 'Italian',
  'ja' => 'Japanese',
  'ko' => 'Korean',
  'ka' => 'Georgian',
  'lt' => 'Lithuanian',
  'lv' => 'Latvian',
  'ms' => 'Malay',
  'nl' => 'Dutch',
  'no' => 'Norwegian',
  'pl' => 'Polish',
  'pt-br' => 'Brazilian Portuguese',
  'pt' => 'Portuguese',
  'ro' => 'Romanian',
  'ru' => 'Russian',
  'sk' => 'Slovak',
  'sl' => 'Slovenian',
  'sq' => 'Albanian',
  'sr' => 'Serbian',
  'sv' => 'Swedish',
  'th' => 'Thai',
  'tr' => 'Turkish',
  'uk' => 'Ukrainian',
  'zh-tw' => 'Chinese Traditional',
  'zh' => 'Chinese Simplified'
);

class SmbWebClientBuild {


# List the people that translates SMB Web Client

function ListTranslators() {
  global $languages;
  print "<?php\n#\n";
  print "# Translators:";
  $lines = file("http://www.nivel0.net/SmbWebClientTranslationData/export.xml");
  $lang = join('|',array_keys($languages));
  $curlang = '';
  foreach ($lines as $line) {
    if (ereg('^\[([0-9]{3})\](.*)$', $line, $regs) OR
        ereg('^\[(.*)\]\[([0-9]{3})\](.*)$', $line, $regs)) {
      continue;
    } elseif (ereg('^\[('.$lang.'})\](.*)$', $line, $regs)) {
      $regs[2] = str_replace("\n", "", $regs[2]);
      if ($curlang == $regs[1])
        print "; {$regs[2]}";
      else {
        print "\n#   {$languages[$regs[1]]}: {$regs[2]}";
        $curlang = $regs[1];
      }
    }
  }
  print "\n?>";
}


# Builds PHP code for $this->mime_types array

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


# Builds PHP code for $this->style array

function MakeStyleArray() {
  print "<?php\n";
  $archivos = explode("\n",str_replace(" ", "", `ls style/*.*`));
?>

# Theme files
# Files are included using base64_encode PHP function

<?php
  print "\$style = array (\n";
  foreach ($archivos as $archivo) if ($archivo <> '') {
    if (! is_readable($archivo)) $encoded = "**error**";
    else {
      $f = fopen($archivo, "r");
      $encoded = base64_encode(fread($f, filesize($archivo)));
      fclose($f);
    }
    print "  '$archivo' => '$encoded',\n";
  }
  print ");\n\n?>";
}


# Builds PHP code for $this->strings array

function MakeStringsArray() {
  print "<?php\n";
  $lines = file("http://www.nivel0.net/SmbWebClientTranslationData/export.xml");
  foreach ($lines as $line) {
    if (ereg('^\[([0-9]{3})\](.*)$', $line, $regs)) {
      $original[intval($regs[1])] = $regs[2];
    } elseif (ereg('^\[(.*)\]\[([0-9]{3})\](.*)$', $line, $regs)) {
      $translation[$regs[1]][intval($regs[2])] = $regs[3];
    }
  }
?>

# Available <?php print count($translation) ?> languages at
# http://wwww.nivel0.net/SmbWebClientTranslation

<?php
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

# Builds SmbWebClient script

function MakeSmbWebClient () {
    include 'php/includes.php';
    foreach ($includes as $archivo) if ($archivo <> '') {
      $a = file("php/".$archivo.".php");
      for ($i = 1; $i < count($a)-1; $i++) {
        $smbwebclient[] = $a[$i];
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