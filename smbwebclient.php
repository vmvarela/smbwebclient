<?php
# -----------------------------------------------------------------
# File:    smbwebclient.php
# Author:  victor m. varela, vmvarela@nivel0.net
#
# $Id: smbwebclient.php,v 1.2 2004/07/07 08:58:39 victorvarela Exp $
#
# Description:
#   This script is a web interface to Windows Networks.
#
#   Getting started:
#   1. You will need smbclient (from SAMBA package) and PHP 4.1+. 
#   2. Change your settings (editing this file)  --  see below
#   3. Copy it to your web server path and run it from your web
#      browser. That's all.
#
#   Go to http://www.nivel0.net/SmbWebClient to get  more
#   information about this script.
#
# Copyright (C) 2003-2004 victor m. varela
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# -----------------------------------------------------------------

class smbwebclient extends samba {

# This is the configuration section. It contains PHP contants that
# give this script its instructions.


# You can set this constant to see a single domain (or workgroup),
# a domain and a server, a domain/server/shared resource
# and (even) a path into a domain/server/shared.
# For example to see only folder Bilbo in 'Bolson' shared resource
# at 'HOBBIT' server in 'TIERRAMEDIA' domain would be:
# var $cfgSambaRoot = 'TIERRAMEDIA/HOBBIT/Bolson/Bilbo';
# Note: Do not put any slash at beginning/end.

var $cfgSambaRoot = '';


# Anonymoys login is disallowed by default.
# If you have public shares in your network, turn on this flag
# i.e. var $cfgAnonymous = 'on';

var $cfgAnonymous = 'off';


# Path at web server to store downloaded files. This script will
# check when it need to update the cached file. This path must be
# writable to the user that runs your web server.
# If you set this value to '' cache will be disabled.
   # Note: this feature is a security risk.

var $cfgCachePath = '';


# This script try to set language from web browser. If browser
# language is not supported you can set a default language.

var $cfgDefaultLanguage = 'en';
 

# Access logfile (apache compatible). You can set to '' to
# disable logging. This file must be writable by the user
# that runs your web server.

var $cfgLogFile = 'smbwebclient.log';


# Default browse server for your network. A browse server is where
# you run smbclient -L subcommand to read available domains and/or
# workgroups. Set to 'localhost' if you are running SAMBA server
# in your web server. Maybe you will need cfgDefaultUser and
# cfgDefaultPassword if no anonymous browsing is allowed.

var $cfgDefaultServer = 'localhost';


# Path to smbclient program. (i.e. var $cfgSmbClient = '/usr/bin/smbclient';)

var $cfgSmbClient = 'smbclient';


# Authentication method with smbclient
# 'SMB_AUTH_ENV' uses USER environment variable (more secure)
# 'SMB_AUTH_ARG' uses -U argument to smbclient

var $cfgAuthMode = 'SMB_AUTH_ENV';


# If you have Apache mod_rewrite installed you can put this
# .htaccess file in same path of smbwebclient.php:
#
#  <IfModule mod_rewrite.c>
#   RewriteEngine on
#   RewriteCond    %{REQUEST_FILENAME}  -d
#   RewriteRule ^(.*/[^\./]*[^/])$ $1/
#   RewriteRule ^(.*)$ smbwebclient.php?path=$1 [QSA,L]
#  </IfModule>
#
# Then you will be able to access to use "pretty" URLs
# i.e: http://server/smbwebclient/DOMAIN/SERVER/SHARE/PATH
#
# To do this, all you have to set is cfgBaseUrl ending with '/'
# (i.e. http://your-server.is/windows/)
#
# Note: Change this if you want mod_rewrite or your script
# name is not smbwebclient.php

var $cfgBaseUrl = 'smbwebclient.php';
# var $cfgBaseUrl = '/vmvarela/smbwebclient/devel/';





# ----------> YOU DO NOT NEED TO EDIT AFTER THIS LINE !!! <-----------





# inline files (included using base64_encode PHP function)

var $cfgInlineFiles = 'on';


# Available 30 languages at
# http://wwww.nivel0.net/SmbWebClientTranslation

var $strings = array (
  'es' => array('Red Windows','Nombre','Tama&ntilde;o','Comentarios','Ultima modificacion','Tipo','d/m/Y H:i','Imprimir un archivo','Borrar elementos seleccionados','Nuevo archivo (subir)','Cancelar trabajos seleccionados','Carpeta','Archivo %s','Enviar un mensaje','Subir','Nueva carpeta','','','','Aceptar'),
  'no' => array('Windows nettverk','Navn','St&oslash;rrelse','Kommentar','Endret','','d/m/Y h:i','Utskrift','Slett valgte','Ny fil','Avbryt valgte','','','','','','','','','Ok'),
  'eu' => array('Windows Sarea','Izena','Tamaina','Komentarioak','Aldatua','','Y/m/d h:i','Inprimatu','Ezabatu aukeratuak','Fitxategi berria','Ezeztatu hautespena','','','','','','','','','Ados'),
  'fr' => array('R&eacute;seau Windows','Nom','Taille','Commentaires','Modifi&eacute;','','d/m/Y h:i','Imprimer','Effacer la s&eacute;lection','Nouveau Fichier','Annuler la s&eacute;lection','','','','','','','','','Valider'),
  'gl' => array('Rede Windows','Nome','Tama&ntilde;o','Comentarios','Modificado','','d/m/Y h:i','Imprimir','Borrar seleccionados','Novo arquivo','Cancelar selecci&oacute;n','','','','','','','','','Aceptar'),
  'el' => array('&#916;&#943;&#954;&#964;&#965;&#959; Windows','&#908;&#957;&#959;&#956;&#945;','&#924;&#941;&#947;&#949;&#952;&#959;&#962;','&#931;&#967;&#972;&#955;&#953;&#945;','&#932;&#961;&#959;&#960;&#959;&#960;&#959;&#953;&#942;&#952;&#951;&#954;&#949;','','m/d/Y h:i','&#917;&#954;&#964;&#973;&#960;&#969;&#963;&#951;','&#916;&#953;&#945;&#947;&#961;&#945;&#966;&#942; &#917;&#960;&#953;&#955;&#949;&#947;&#956;&#941;&#957;&#969;&#957;','&#925;&#941;&#959; &#913;&#961;&#967;&#949;&#943;&#959;','&#913;&#954;&#973;&#961;&#969;&#963;&#951; &#917;&#960;&#953;&#955;&#949;&#947;&#956;&#941;&#957;&#969;&#957;','','','','','','','','',''),
  'it' => array('Rete Windows','Nome','Dimensione','Commenti','Modificato','','d/m/Y h:i','Stampa','Rimuovi l\'oggetto selezionato','Nuovo File','Cancella l\'oggetto selezionato','','','','','','','','',''),
  'pl' => array('Sie&#263; Windows','Nazwa','Wielko&#347;&#263;','Komentarz','Zmodyfikowany','','d/m/Y h:i','Drukuj','Usu&#324; zaznaczone','Nowy plik','Anuluj zaznaczone','','','','','','','','','OK'),
  'ro' => array('Reteaua Windows','Nume','Marime','Comentarii','Modificat','','m/d/Y h:i','Print','Sterge selectia','Fisier Nou','Renunta','','','','','','','','',''),
  'ru' => array('&#1057;&#1077;&#1090;&#1100; Windows','&#1048;&#1084;&#1103;','&#1056;&#1072;&#1079;&#1084;&#1077;&#1088;','&#1050;&#1086;&#1084;&#1084;&#1077;&#1085;&#1090;&#1072;&#1088;&#1080;&#1081;','&#1048;&#1079;&#1084;&#1077;&#1085;&#1077;&#1085;','','d/m/Y h:i','&#1055;&#1077;&#1095;&#1072;&#1090;&#1100;','&#1059;&#1076;&#1072;&#1083;&#1080;&#1090;&#1100; &#1074;&#1099;&#1076;&#1077;&#1083;&#1077;&#1085;&#1085;&#1099;&#1077;','&#1053;&#1086;&#1074;&#1099;&#1081; &#1092;&#1072;&#1081;&#1083;','&#1054;&#1090;&#1084;&#1077;&#1085;&#1080;&#1090;&#1100; &#1074;&#1099;&#1076;&#1077;&#1083;&#1077;&#1085;&#1080;&#1077;','','','','','','','','','&#1054;&#1082;'),
  'nl' => array('Windows Netwerk','Naam','Grootte','Commentaar','Gewijzigd','','d/m/Y h:i','Afdrukken','Selectie verwijderen','Nieuw bestand','Selectie afbreken','','','','','','','','','OK'),
  'cs' => array('S&iacute;t Windows','N&aacute;zev','Velikost','Pozn&aacute;mka','Zmeneno','','mesic/den/rok','Tisk','Smazat Vybran&eacute;','Nov&yacute; soubor','Zrusit v&yacute;ber','','','','','','','','','ok'),
  'da' => array('Windows Netv&aelig;rk','Navn','St&oslash;rrelse','Kommentar','&AElig;ndret','','(m.d.Y t:i)','Udskriv','Slet valgte','Ny fil','Afbryd valgte','','','','','','','','','O.k.'),
  'sv' => array('Windows N&auml;tverk','Namn','Storlek','Kommentar','&Auml;ndrad','','','Skriv ut','Ta bort markerad','Ny fil','Avbryt markerad','','','','','','','','','Ok'),
  'de' => array('Windows Netzwerk','Name','Gr&ouml;&szlig;e','Bemerkung(en)','Ge&auml;ndert','','d.m.Y H:i','Drucken','Markierte L&ouml;schen','Neue Datei','Abbrechen','','','','','','','','','O.K.'),
  'ja' => array('&#12493;&#12483;&#12488;&#12527;&#12540;&#12463;','&#21517;&#21069;','&#12469;&#12452;&#12474;','&#12467;&#12513;&#12531;&#12488;','&#26356;&#26032;&#26085;&#26178;','','','&#12503;&#12522;&#12531;&#12488;','&#36984;&#25246;&#12375;&#12383;&#12418;&#12398;&#12434;&#21066;&#38500;','&#26032;&#35215;&#20316;&#25104;','&#36984;&#25246;&#12375;&#12383;&#12418;&#12398;&#12434;&#12461;&#12515;&#12531;&#12475;&#12523;','','','','','','','','','OK'),
  'tr' => array('Windows A&#287;&#305;','&#304;sim','Boyut','Yorumlar','De&#287;i&#351;tirilme Tarihi','','m/d/Y h:i','Yazd&#305;r','Se&ccedil;imi sil','Yeni dosya','Se&ccedil;im &#304;ptal','','','','','','','','','Tamam'),
  'ca' => array('Xarxa Windows','Nom','Tamany','Comentaris','Modificat','','','Imprimeix','Esborra els seleccionats','Nou arxiu','Cancel&middot;la selecci&oacute;','','','','','','','','','D\'acord'),
  'pt' => array('Rede Windows','Filipe Lu&iacute;s Ferreira','xl','redes','Modificado','','12/06/2004','Imprimir','Apagar selecionados','Novo arquivo','Cancelar selecionados','','','','','','','','',''),
  'eo' => array('Reto  de Windows','Nomo','Grandeco','komentaroj','Modifii','','','Presi','','Nova dosiero','Nuligi','','','','','','','','','Okej'),
  'et' => array('Windowsi v&otilde;rk','Nimi','Suurus','Kommentaarid','Muudetud','','d/m/Y h:i','Tr&uuml;ki','Kustuta valitud','Uus fail','T&uuml;hista valitud','','','','','','','','','Korras'),
  'uk' => array('&#1052;&#1077;&#1088;&#1077;&#1078;&#1072; &#1042;&#1110;&#1085;&#1076;&#1086;&#1074;&#1079;','&#1030;&#1084;\'&#1103;','&#1056;&#1086;&#1079;&#1084;&#1110;&#1088;','&#1050;&#1086;&#1084;&#1077;&#1085;&#1090;&#1072;&#1088;&#1110;','&#1047;&#1084;&#1110;&#1085;&#1077;&#1085;&#1080;&#1081;','','d.m.Y h:i','&#1044;&#1088;&#1091;&#1082;&#1091;&#1074;&#1072;&#1090;&#1080;','&#1042;&#1080;&#1076;&#1072;&#1083;&#1080;&#1090;&#1080; &#1074;&#1110;&#1076;&#1084;&#1110;&#1095;&#1077;&#1085;&#1077;','&#1053;&#1086;&#1074;&#1080;&#1081; &#1092;&#1072;&#1081;&#1083;','&#1042;&#1110;&#1076;&#1084;&#1110;&#1085;&#1080;&#1090;&#1080; &#1074;&#1110;&#1076;&#1084;&#1110;&#1095;&#1077;&#1085;&#1077;','','','','','','','','','&#1043;&#1072;&#1088;&#1072;&#1079;&#1076;'),
  'bg' => array('&#1059;&#1080;&#1085;&#1076;&#1086;&#1091;&#1089; &#1084;&#1088;&#1077;&#1078;&#1072;','&#1048;&#1084;&#1077;','&#1056;&#1072;&#1079;&#1084;&#1077;&#1088;','&#1050;&#1086;&#1084;&#1077;&#1085;&#1090;&#1072;&#1088;','&#1055;&#1088;&#1086;&#1084;&#1103;&#1085;&#1072;','','','&#1055;&#1077;&#1095;&#1072;&#1090;','&#1048;&#1079;&#1090;&#1088;&#1080;&#1081; &#1080;&#1079;&#1073;&#1088;&#1072;&#1085;&#1080;&#1090;&#1077;','&#1053;&#1086;&#1074; &#1092;&#1072;&#1081;&#1083;','&#1054;&#1090;&#1082;&#1072;&#1078;&#1080; &#1080;&#1079;&#1073;&#1086;&#1088;','','','','','','','','','&#1055;&#1086;&#1090;&#1074;&#1098;&#1088;&#1076;&#1080;'),
  'sr' => array('Vindovs mreza','Ime','velicina','komentari','promenjen','','d/m/Y h:i','Odstampaj','Obrisi selekciju','Nova datoteka','Odustajem od izabranog','','','','','','','','','Prihvatam'),
  'hr' => array('Windows mreža','Naziv','Veli&#269;ina','Komentar','Modificirano','','','Ispiši','Obriši selektirano','Nova datoteka','','','','','','','','','','U redu'),
  'lv' => array('Windows T&#299;kls','Nosaukums','Izm&#275;rs','Koment&#257;ri','Izmain&#299;ts','','m/d/g s:m','Druk&#257;t','Dz&#275;st izv&#275;l&#275;tos','Jauns fails','Atcelt izv&#275;l&#275;tos','','','','','','','','','Ok'),
  'fi' => array('Windows Verkko','Nimi','Koko','Kommentit','Muokattu','','','Tulosta','Poista valitut','Uusi tiedosto','Peruuta valinta','','','','','','','','',''),
  'hu' => array('Windows h&aacute;l&oacute;zat','N&eacute;v','M&eacute;ret','Megjegyz&eacute;s','M&oacute;dos&iacute;tva','','h/n/&Eacute; &oacute;:p','Nyomtat','Kiv&aacute;lasztott t&ouml;rl&eacute;se','&Uacute;j &aacute;llom&aacute;ny','Kijel&ouml;l&eacute;s elvet&eacute;se','','','','','','','','','Rendben'),
  'pt-br' => array('Rede Windows','Nome','Tamanho','Coment&aacute;rios','Modificado','','d/m/Y h:i','Imprimir','Apagar selecionado','Novo arquivo','Cancelar Sele&ccedil;&atilde;o','','','','','','','','','OK'),
  'th' => array('&#3619;&#3632;&#3610;&#3610;&#3648;&#3588;&#3619;&#3639;&#3629;&#3586;&#3656;&#3634;&#3618;&#3586;&#3629;&#3591;&#3623;&#3636;&#3609;&#3650;&#3604;&#3623;&#3660;','&#3594;&#3639;&#3656;&#3629;','&#3586;&#3609;&#3634;&#3604;','&#3588;&#3623;&#3634;&#3617;&#3648;&#3627;&#3655;&#3609;','&#3611;&#3619;&#3633;&#3610;&#3611;&#3619;&#3640;&#3591;&#3648;&#3617;&#3639;&#3656;&#3629;','','&#3648;&#3604;&#3639;&#3629;&#3609;/&#3623;&#3633;&#3609;/&#3611;&#3637; &#3594;&#3633;&#3656;&#3623;&#3650;&#3617;&#3591;','&#3614;&#3636;&#3617;&#3614;&#3660;','&#3621;&#3610;&#3626;&#3636;&#3656;&#3591;&#3607;&#3637;&#3656;&#3648;&#3621;&#3639;&#3629;&#3585;&#3652;&#3623;&#3657;','&#3626;&#3619;&#3657;&#3634;&#3591;&#3649;&#3615;&#3657;&#3617;','&#3618;&#3585;&#3648;&#3621;&#3636;&#3585;&#3626;&#3636;&#3656;&#3591;&#3607;&#3637;&#3656;&#3648;&#3621;&#3639;&#3629;&#3585;&#3652;&#3623;&#3657;','','','','','','','','','&#3605;&#3585;&#3621;&#3591;'),
  'en' => array('Windows Network','Name','Size','Comments','Modified','Type','m/d/Y H:i','Print a file','Delete selected items','New file (upload)','Cancel selected jobs','File Folder','File %s','Send a popup message','Up','New folder','','','','Ok'),
);





# supported languages
var $languages = array (
  'af' => 'af|afrikaans',
  'ar' => 'ar([-_][[:alpha:]]{2})?|arabic',
  'az' => 'az|azerbaijani',
  'bg' => 'bg|bulgarian',
  'bs' => 'bs|bosnian',
  'ca' => 'ca|catalan',
  'cs' => 'cs|czech',
  'da' => 'da|danish',
  'de' => 'de([-_][[:alpha:]]{2})?|german',
  'el' => 'el|greek',
  'en' => 'en([-_][[:alpha:]]{2})?|english',
  'eo' => 'eo|esperanto',
  'es' => 'es([-_][[:alpha:]]{2})?|spanish',
  'et' => 'et|estonian',
  'eu' => 'eu|basque',
  'fa' => 'fa|persian',
  'fi' => 'fi|finnish',
  'fr' => 'fr([-_][[:alpha:]]{2})?|french',
  'gl' => 'gl|galician',
  'he' => 'he|hebrew',
  'hi' => 'hi|hindi',
  'hr' => 'hr|croatian',
  'hu' => 'hu|hungarian',
  'id' => 'id|indonesian',
  'it' => 'it|italian',
  'ja' => 'ja|japanese',
  'ko' => 'ko|korean',
  'ka' => 'ka|georgian',
  'lt' => 'lt|lithuanian',
  'lv' => 'lv|latvian',
  'ms' => 'ms|malay',
  'nl' => 'nl([-_][[:alpha:]]{2})?|dutch',
  'no' => 'no|norwegian',
  'pl' => 'pl|polish',
  'pt-br' => 'pt[-_]br|brazilian portuguese',
  'pt' => 'pt([-_][[:alpha:]]{2})?|portuguese',
  'ro' => 'ro|romanian',
  'ru' => 'ru|russian',
  'sk' => 'sk|slovak',
  'sl' => 'sl|slovenian',
  'sq' => 'sq|albanian',
  'sr' => 'sr|serbian',
  'sv' => 'sv|swedish',
  'th' => 'th|thai',
  'tr' => 'tr|turkish',
  'uk' => 'uk|ukrainian',
  'zh-tw' => 'zh[-_]tw|chinese traditional',
  'zh' => 'zh|chinese simplified'
);

# MIME types from file name extension
var $mimeTypes = array (
  'mdb' => 'application/msaccess', 
  'doc' => 'application/msword',
  'dot' => 'application/msword',
  'bin' => 'application/octet-stream', 
  'pdf' => 'application/pdf',
  'pgp' => 'application/pgp-signature', 
  'ps' =>  'application/postscript',
  'rtf' => 'text/rtf',
  'xls' => 'application/vnd.ms-excel',
  'ppt' => 'application/vnd.ms-powerpoint',
  'pps' => 'application/vnd.ms-powerpoint',
  'pot' => 'application/vnd.ms-powerpoint', 
  'zip' => 'application/zip', 
  'deb' => 'application/x-debian-package',
  'dvi' => 'application/x-dvi',
  'gtar' => 'application/x-gtar',
  'tgz' => 'application/x-gtar', 
  'taz' => 'application/x-gtar',
  'ica' => 'application/x-ica',
  'js' => 'application/x-javascript', 
  'lha' => 'application/x-lha',
  'lzh' => 'application/x-lzh',
  'lzx' => 'application/x-lzx', 
  'com' => 'application/x-msdos-program',
  'exe' => 'application/x-msdos-program', 
  'bat' => 'application/x-msdos-program',
  'dll' => 'application/x-msdos-program',
  'msi' => 'application/x-msi', 
  'pac' => 'application/x-ns-proxy-autoconfig', 
  'ogg' => 'application/x-ogg',
  'swf' => 'application/x-shockwave-flash', 
  'swfl' => 'application/x-shockwave-flash',
  'tar' => 'application/x-tar', 
  'tex' => 'text/x-tex',
  'man' => 'application/x-troff-man', 
  'crt' => 'application/x-x509-ca-cert', 
  'au' => 'audio/basic',
  'snd' => 'audio/basic', 
  'mid' => 'audio/midi',
  'midi' => 'audio/midi',
  'kar' => 'audio/midi', 
  'mpga' => 'audio/mpeg',
  'mpega' => 'audio/mpeg',
  'mp2' => 'audio/mpeg', 
  'mp3' => 'audio/mpeg',
  'm3u' => 'audio/x-mpegurl',
  'ra' => 'audio/x-realaudio',
  'rm' => 'audio/x-pn-realaudio', 
  'ram' => 'audio/x-pn-realaudio',
  'pls' => 'audio/x-scpls',
  'wav' => 'audio/x-wav', 
  'bmp' => 'image/x-ms-bmp', 
  'gif' => 'image/gif',
  'ico' => 'image/ico',
  'ief' => 'image/ief',
  'jpeg' => 'image/jpeg', 
  'jpg' => 'image/jpeg',
  'jpe' => 'image/jpeg',
  'pcx' => 'image/pcx', 
  'png' => 'image/png',
  'svg' => 'image/svg+xml',
  'svgz' => 'image/svg+xml',
  'tiff' => 'image/tiff',
  'tif' => 'image/tiff',
  'wbmp' => 'image/vnd.wap.wbmp',
  'ras' => 'image/x-cmu-raster',
  'jng' => 'image/x-jng',
  'pnm' => 'image/x-portable-anymap',
  'pbm' => 'image/x-portable-bitmap', 
  'pgm' => 'image/x-portable-graymap',
  'ppm' => 'image/x-portable-pixmap',
  'xbm' => 'image/x-xbitmap',
  'xpm' => 'image/x-xpixmap',
  'xwd' => 'image/x-xwindowdump', 
  'wrl' => 'x-world/x-vrml',
  'vrml' => 'x-world/x-vrml',
  'csv' => 'text/comma-separated-values',
  'css' => 'text/css',
  'htm' => 'text/html',
  'html' => 'text/html',
  'xhtml' => 'text/html',
  'asc' => 'text/plain',
  'txt' => 'text/plain', 
  'text' => 'text/plain',
  'diff' => 'text/plain',
  'rtx' => 'text/richtext', 
  'tsv' => 'text/tab-separated-values',
  'wml' => 'text/vnd.wap.wml',
  'wmls' => 'text/vnd.wap.wmlscript', 
  'xml' => 'text/xml',
  'xsl' => 'text/xml',
  'fli' => 'video/fli',
  'gl' => 'video/gl',
  'mpeg' => 'video/mpeg', 
  'mpg' => 'video/mpeg',
  'mpe' => 'video/mpeg',
  'qt' => 'video/quicktime', 
  'mov' => 'video/quicktime',
  'asf' => 'video/x-ms-asf',
  'asx' => 'video/x-ms-asf',
  'avi' => 'video/x-msvideo',
  'ice' => 'x-conference/x-cooltalk',
  'vrm' => 'x-world/x-vrml'
);


# Theme files
# Files are included using base64_encode PHP function

# Theme files
# Files are included using base64_encode PHP function

var $inlineFiles = array (
  'style/disk.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHDgIvgzUxgQAAAjBJREFUeJytlM9rE1EQxz9NtLWSi6Ft1pZicbehiMRAD6JZUGiFhRwEQfAk6kUPXgo5+KMi/gW1Fg9e2kPRQ0SK2NIFjyaNFGvqraZ5EJpD06CkMTHFpPF5qI1Zt4sizmnel5nPm5k3PPhP1vK7sLYwInf9mu8mqqraYv4IWlsYkb2nrgJVALKJp7YEJ3hD+BgdkGr4Me6D6R1BdgFeaPkCfAKqZBMrvEpqGIZhg+1rPrjlFlQqP08ZIENq7onl5uFO2F6aQAghm2EWUH2zgPt7gdTbaENTz8/jbs0DdSjVAEiZ1zFN0wKzgCobH1gXs/gvzgMpYAtINEW0kjJHiUz3o+tlS6UW0LqY5fDxG1BfBD5jM9kKgKIoBINBHFsD2O89BqWkHQKkXk9x99kRBk8eRVVV54r8xuSOs/m1odW3vyHevQQgMt1PIBAgFAqhaZrzq1XziwBk3kebZSLT/SiKgq5rhEIhdF133iMhhNxeCjcSL1+5xuidW/j9foLBILquo2ma46ZbxHg8LmOxGEN9c3R0n0DWy6wkZsh3j2MYRsHn83n3ggC4dp3nL2Zkm7eTs+ELeLwqPfoj+s7szGxg8DQbxeKhe/cfSCGE3AvUmNHU1CS+nl6qNbgdXqCUvITLVUe6DzAxNkZ7GyTexFC6OmxbbQFNjD/ENE3S6TTJ+CrJ+GojqJjP0q4onBsecurMOiMhhCyXy+RyOZaXl8nlcsCvBVQUBY/H89dfyz/ZD84Fy8JJpTM+AAAAAElFTkSuQmCC',
  'style/dotdot.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHDyEuS1kcQQAAAlJJREFUeJy9kl9IU2EUwH+zbdd73fTOa+mCdOoqExcVFOSSLKWHwrCnHuqxIBN6XVK9hVQPEUTWWyBSvYigID0EFQqBf0DTyLLplmL+u013tznn5u2lBjZdENR5+77D+X2/c74D/zMa2xr0qntV+ujXYf2vId1DXXqmx6jbn1j1/CZFv9PdvCnM8CdQ8U2HvuYMUKBYkI0CU2NBhOBOHp9/iLvseLI+Ix2ksa1B1+QFChQL2yUJxSpRXeUg27FMTUstTe2epF2KkbfjcDLpfDlAVq4Z07YMDh204yzKxWQy0fV8hqBxHoDA7RUDgPF3SE75JWTHEbS4yheakrnT3kn2OfMRRZFC2y56PP4NEhtAAMru/WDoQzaDXH8XUIAwgVunEEURSZRYX1tMGUMKiNgkGGI/Dx8AmOhupXhdRxIlwuE1lCwlOYLSc/2prQEQDJCIzRFZniWk+liNRskpv4rkfYpZEPg8rnLCdZaSmkZ8vdeB/i2MlkPMeN9h3XsFe/kxIAio+MOfEKVCBgf9NF8oQ/vWiaAcBVo2ByVWY5C9H9lhADoB8KkqViWT98PTuDPzcFhiTA60U3KyY+s9+r44iuKsB80HmgaaRuubt0SjcYZfjXO/ro6IOoQ5tzb9sOPxGJIkQGglefegt4fqwiIe1VeSmBlhLjBLyZm+9N+PtQyCYxAJoS1NE1qa4kWFgJIYY2EkgpjvxlJ8DbiY3sgs7+Hjaw+R0DyCzYW0w42r8jKCzYViP2AAP/As5f0Ner92I6viBoKtotSW55xIqfjX8QPSvc+A1HHT7gAAAABJRU5ErkJggg==',
  'style/down.png' => 'iVBORw0KGgoAAAANSUhEUgAAAAsAAAALCAYAAACprHcmAAAABmJLR0QA6wDqANsTMxObAAAACXBIWXMAAAsRAAALEQF/ZF+RAAAAB3RJTUUH1AUHCyYQwnA/8QAAAEhJREFUeJxjYCABMDIwMDCsWTHzPyGFIRHpjIwwDj4NIRHpjMgmw8SxaWCEamBANhmbBrhCOAeHBhSFGIrRNKAoxAmQNQxCAABCrxj04y1lDgAAAABJRU5ErkJggg==',
  'style/favicon.ico' => 'AAABAAEAICAAAAAAAACoCAAAFgAAACgAAAAgAAAAQAAAAAEACAAAAAAAgAQAAAAAAAAAAAAAAAEAAAAAAAAAAAAA4vTCAOvOhAB70H4A5a1KAEy3VADI46YAvo8MAN3d3ACfXwwAtbWwAAx5BACXkowAlHBTAMbGxgD6+vkAhl9BAP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgOCgoKCggIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgODAwMDAwMDQwKAAAAAAAAAAAAAAAAAAAAAAAAAAAICgoOCAgPCA4KDAwMCAAAAAAAAAAAAAAAAAAAAAAAAAoKCAgPDw8PDw8ODA0MAAAAAAAAAAAAAAAAAAAAAAAOCggPCA8PDw8PDw8ICg0OAAAAAAAAAAAAAAAAAAAAAAoICAgIDw8PDw8PDw8ODAwAAAAAAAAAAAAAAAAAAAAOCg8IDwgICAgIDw8PDwgMDAAAAAAAAAAAAAAAAAAADgwIDwgPCAgPCA8ICA8PDwoMCAAAAAAAAAAAAAAAAAoHDAgPDwgIDwgICAgIDw8ICg0OAAAAAAAAAAAAAAAKEAkMCA8PDw8IDwgPCAgIDwgKCQ0IAAAAAAAAAAAAChAJCQwPDw8PCA8IDwgPCAgPCAwQEAwAAAAAAAAAAAANCQkJDQ8PDw8PDwgPCAgIDw8ODRAQEAoAAAAAAAAICgkJCQkMDw8PDw8PDw8PCA8ICAwQEBAQDAAAAAAAAAACCQkJCQ0ODw8PDw8PDwgPCAgOEBAQEBAQCAAAAAAAAQ0JCQkJCg4ODg8PCAgIDwgPDgwJCRAQEBAKAAAAAAAGDQkJCQkEDw8IDgoNDQ4IDgoKEAkJEAsQEAwAAAAAAAIJCQkJCQwIDw8PDBANDggIBg0LCwkLEBAJDAAAAAAAAgkJCQcJBAoPDw8KDQwPDw4MCwsLCwkLEAkMAAAAAAAKBwkMDgoLBQwKCAoKCAEODAcLCwsLCQsQCQwAAAAAAAIHDQoMCgoFBQUKDgUCBAcHBwcLCwsJCQkJDAAAAAAABgcJBwwFCg4KDg8CBAIEBAcHBwcLCwsLCwkMAAAAAAABBAkHBQUFAwgBAwMDAgQEBAcHBwsLCwsLCQgAAAAAAAACBwcFBQUDAwMDAwMCAgQEBwcLBwsLCwsNCAAAAAAAAAEHBwUFAwMDBgMDBgYGBAQEBwcLCwsLCQwAAAAAAAAAAQMFBQMDAwYGBgEBBgMCBAcHBwcLCwsECAAAAAAAAAAAAQUFAwMDBgYBAQEGAwUFBQcHBwkLBwoAAAAAAAAAAAAAAQUDAwMGAQEGBgMDAwUHBwkHCQcKAAAAAAAAAAAAAAAABgUDAwYDBgYGAwMFBQcHBwcHBAAAAAAAAAAAAAAAAAAAAQYDAwYGBgMDAwUFBAcHBwIAAAAAAAAAAAAAAAAAAAAAAQYDAgMGAwMDBQUHBwQIAAAAAAAAAAAAAAAAAAAAAAAAAAEBAgIDBAQFAgIBAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEBAQEAAAAAAAAAAAAAAAAAAP/4B///4AP//8AA///AAP//gAB//4AAf/8AAH/+AAA//AAAP/gAAB/wAAAf8AAAD8AAAA/gAAAHwAAAB8AAAAfAAAAHwAAAB8AAAAfAAAAHwAAAB8AAAAfgAAAH4AAAD+AAAA/wAAAf+AAAP/wAAH/+AAD//wAB///AB////D//',
  'style/file.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHDh0ZgdWqhgAAAPZJREFUeJyt0jESgjAQBdAfh8KextbSnoPYJL2NuYONkgJmOIJHgE6OkFYmnR5BbkC5NojGEIyOW2122DebJcCfgj2SLMsopCGOY0gp2Xs9ej1sNuuP0OmkwTmnqqoszIIWi1XATBplWUIIYWGzgE4nhBAAAM75sI7o/aM8zz9CdV1Da42iKIaaAwHAbheDzkewZDsKzedAkiRWzXs1H+KL0YmI/EjbXrFcuvWJZTOk7PmHmfNyAqGUAftXhKYxL7QnQtrnRL188EujO2rbKwBA3i5DfpMX/zhjkFIKSqnJpiCo67qvEQdqmuYnxIKMMTDG/Az9Le5YqEcLs1eIzgAAAABJRU5ErkJggg==',
  'style/folder.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHDhwE+8j3HgAAAY1JREFUeJzNkz9IW1EUxn+XCo9AB6dUx5fBQQQpShMcOhQyubZroYubi+AYh64OQoeCrnX0YbFjoEMRfaWZHDp0yIOACJlj8vLnnNPhmWcSY4yL+MEdLufe7/7u4Tvw3OQGN5X9VTMzVBVVZW79O77vu/suj9WNyZB+7S1aGIY2NVFlf9VWNv4AByPlDX7uLiAiiEhCaoqIsPzpfIh2BsDMIP4CIiNGe7zb/nfn9ePSK4IgoFqtWi6XcynR76+v7c2H99DtcXZ0NBa92+3S6/XSZSQ/Xvp4iu/7bgZAVeG6ydnJCWubFw835OozzO9wuPWSIAhuv6aq0Gjged6YPt1VO4rw5g8QEWq12q2RiEAcJ70CiOOJRq1WC+8GwJyNEAGdTic52Wg8aDTbv/eCEaJBo+vmRKNmM6nfa9Rut6cmgiQ2ziVRcgBRFNnfb2/TsKlqGsLBfX90VBUz48flOvl8nlKp5NJkhmFo5XKZer0+kWZQ2WyWYrFIoVBwQwMZRZH1sadRJpN5/FA/mf4DyGb4kNXbm0QAAAAASUVORK5CYII=',
  'style/logout.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QAAAAAAAD5Q7t/AAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AcFCCA0eqZZFAAAA3dJREFUeJw9k01rnFUYhq/nnDNfmXxM0480gtG2ojRKtbVgq6BCceVCRNClC/EfdCfozu4F0bWCSHduiohULNq6cFG10DbV1tDEkJBJMpnMzPu+5zzncTHRP3DDfV33Ld98eMbOvvAyOtompxE5laAVWSOCYRhkxSxBTlhWyIqmgliOKMIR1nYS4ey5V6hNPUL9wONo0UPLHpYqLEcsJ8iKb07jmx3AoeU2sbdCGm6C77PX6wIzhFz0cLPHwXlcqGOpTrYMGCKe5vzTTCycJ7SPIL6OFluU3T/p3bpMqu7gfI297gohpxIt+4gL5Dgip4IcS0xLWvNnmF58E0zp3/uWtLdO49BTTJ64gK9PEq9/guzexflAyHGIFj3EOXIsyOWQHAt8c5r2wotYHLL23Qek4SbiA4Plnxj8/SNHX/uY6SdfZ2t9GXGeYFqR4wARP2ajYz71zgK1zgLdXz4l9lepTc1x+KWLWK5Y/+Ej9u5/T3NukdrUHMgGwTRiqQTx2L4VywlDEHFUvWVAyVWP4eoNwLDUp9y6R3vmcVyoI0Awi+RYIC6AjVWzDxvANSYRyeRyk93bX2G5RJxR7zyKaYHlAiTjLJZYKjDd103GN6dpHT2FhCauVkM8SADYQ2RI49Bx2o+9SrWzhA5XEVOC5YhpCSKAw7dm6DzzDq3559j69TPKzVu05p/FtSbQ4T+05k4zffI94u5f7N2/jOUeIolgWv7PKLQP0Vl8i9bRU/Rufc3w4TVac6c4eO7ieGNZsbhD2b1B7/bnxN3fcS4jKCFrSdaS+uQcMyffYGLhPNu/fcngwVVMC6ZPvo3zNfpLX6CjZbRaJ/ZuokUX8RlQsEhAK0wLJhbO0z5+gf7SFQYPrlKfPUGYOoz4AEAqVhmtXUGkAgpwCSTtB6X/dlTgG1OkwQb9pSs0Di8y+/z7+NYs4jyj1auUG9cRMZCMSEZyAqr9Qysha4XEEVkToXWAA6ffpXHwCeLuCmX3DqYDtm9eAtlFfEQkYiQsJSwplhI5GaGqKoIb0l++jmZDRBis/cHg4TXi9l3wiqtFnK8QV+3XGYfEkRIrI6ohP19aNB1s4EITV2uMh4mN3+8y4jK+lnCSwEXMEmIRtKIqlZ0dY1uPETZHTTQdob+xgvMBcR4zEMA5Q5whJIJTIKN5rFssk5JB+xgTEx3+Bc2aGOTSMgcBAAAAAElFTkSuQmCC',
  'style/page.thtml' => 'PD94bWwgdmVyc2lvbj0iMS4wIj8+CjwhRE9DVFlQRSBodG1sCiAgICAgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjEvL0VOIiAKICAgICAiaHR0cDovL3d3dy53My5vcmcvVFIveGh0bWwxMS9EVEQveGh0bWwxMS5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CjxoZWFkPgogIDx0aXRsZT57dGl0bGV9PC90aXRsZT4KICA8bGluayByZWw9Imljb24iIGhyZWY9IntmYXZpY29ufSIgdHlwZT0iaW1hZ2UvaWNvIiAvPgogIDxsaW5rIHJlbD0ic2hvcnRjdXQgaWNvbiIgaHJlZj0ie2Zhdmljb259IiAvPgogIDxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+CiAgPCEtLQogICAgQk9EWSwgVEFCTEUgewogICAgICBjb2xvcjogYmxhY2s7CiAgICAgIGJhY2tncm91bmQ6IHdoaXRlOwogICAgICBmb250LXNpemU6IDhwdDsKICAgICAgZm9udC1mYW1pbHk6IFRhaG9tYSwgQXJpYWwsIEhlbHZldGljYTsKICAgICAgbWFyZ2luLXRvcDogMHB4OwogICAgICBtYXJnaW4tbGVmdDogMHB4OwogICAgICBtYXJnaW4tcmlnaHQ6IDBweDsKICAgICAgd2lkdGg6IDEwMCU7CiAgICB9CiAgICBJTlBVVCwgU0VMRUNUIHsKICAgICAgZm9udC1zaXplOiA4cHQ7CiAgICB9CiAgICBIUiB7CiAgICAgIG1hcmdpbi10b3A6IDEwcHg7CiAgICB9CiAgICBBIHsKICAgICAgY29sb3I6IGJsYWNrOwogICAgICB0ZXh0LWRlY29yYXRpb246IG5vbmU7CiAgICAgIHBhZGRpbmctbGVmdDogM3B4OwogICAgICBwYWRkaW5nLXJpZ2h0OiAzcHg7CiAgICB9CiAgICBBOkhPVkVSIHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogIzMxNmFjNTsKICAgICAgY29sb3I6IHdoaXRlOwogICAgICBwYWRkaW5nLXRvcDogM3B4OwogICAgICBwYWRkaW5nLWJvdHRvbTogM3B4OwogICAgfQogICAgVEggewogICAgICB0ZXh0LWFsaWduOiBsZWZ0OwogICAgICB3aGl0ZS1zcGFjZTogbm93cmFwOwogICAgICBiYWNrZ3JvdW5kLWNvbG9yOiAjZWVlYWQ4OwogICAgICBib3JkZXItYm90dG9tOiAzcHggc29saWQgI2Q2ZDJjMjsKICAgICAgYm9yZGVyLWxlZnQ6IDFweCBzb2xpZCB3aGl0ZTsKICAgICAgYm9yZGVyLXJpZ2h0OiAxcHggc29saWQgI2Q2ZDJjMjsKICAgICAgcGFkZGluZy1sZWZ0OiAxMHB4OwogICAgICBwYWRkaW5nLXJpZ2h0OiAzcHg7CiAgICAgIHBhZGRpbmctdG9wOiAzcHg7CiAgICAgIGZvbnQtd2VpZ2h0OiBub3JtYWw7CiAgICB9CiAgICBUSDpIT1ZFUiB7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmYWY4ZjM7CiAgICAgIGJvcmRlci1ib3R0b206IDNweCBzb2xpZCAjZmNjMjQ3OwogICAgfQogICAgVEggQTpIT1ZFUiB7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmYWY4ZjM7CiAgICAgIGNvbG9yOiBibGFjazsKICAgIH0KICAgIFRILmxhbmd1YWdlIHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogIzIyNWFkOTsKICAgICAgYm9yZGVyLWJvdHRvbTogM3B4IHNvbGlkICMzODg4ZTk7CiAgICAgIGJvcmRlci1sZWZ0OiAxcHggc29saWQgIzM4ODhlOTsKICAgICAgYm9yZGVyLXJpZ2h0OiAxcHggc29saWQgYmxhY2s7CiAgICAgIHBhZGRpbmctcmlnaHQ6IDEwcHg7CiAgICAgIGNvbG9yOiB3aGl0ZTsKICAgIH0KICAgIFRILnRvb2xiYXIgewogICAgICBiYWNrZ3JvdW5kLWNvbG9yOiAjMTI4YmU2OwogICAgICBib3JkZXItYm90dG9tOiAzcHggc29saWQgIzE5YjhmMjsKICAgICAgYm9yZGVyLWxlZnQ6IDFweCBzb2xpZCAjMTliOGYyOwogICAgICBib3JkZXItcmlnaHQ6IDFweCBzb2xpZCBibGFjazsKICAgICAgcGFkZGluZy1yaWdodDogMTBweDsKICAgICAgY29sb3I6IHdoaXRlOwogICAgfQogICAgVEQgewogICAgICB3aGl0ZS1zcGFjZTogbm93cmFwOwogICAgICBwYWRkaW5nLXJpZ2h0OiAxMHB4OwogICAgICBwYWRkaW5nLWxlZnQ6IDVweDsKICAgICAgcGFkZGluZy10b3A6IDBweDsKICAgICAgcGFkZGluZy1ib3R0b206IDBweDsKICAgICAgdmVydGljYWwtYWxpZ246IG1pZGRsZTsKICAgIH0KICAgIFRELm9yZGVyLWJ5IHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2Y3ZjdmNzsKICAgIH0KICAgIFRELmNoZWNrYm94IHsKICAgICAgd2lkdGg6IDIwcHg7CiAgICAgIHRleHQtYWxpZ246IHJpZ2h0OwogICAgICBwYWRkaW5nLWxlZnQ6IDBweDsKICAgICAgcGFkZGluZy1yaWdodDogMHB4OwogICAgfQogICAgVEguY2hlY2tib3ggewogICAgICB3aWR0aDogMjBweDsKICAgICAgdGV4dC1hbGlnbjogcmlnaHQ7CiAgICAgIHBhZGRpbmctbGVmdDogMHB4OwogICAgICBwYWRkaW5nLXJpZ2h0OiAwcHg7CiAgICB9CiAgLS0+CiAgPC9zdHlsZT4KPC9oZWFkPgo8Ym9keT4KCntjb250ZW50fQoKPC9ib2R5Pgo8L2h0bWw+',
  'style/printer.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHDhQANHy5DwAAArlJREFUeJytkU1PE2EUhZ+ZMtOiZbSNpCQCBaVBClOREhI0GglBGtcmGiM7o3sX/gJW7k1kK5su2LiSSMAQ+Yg1tZI0RokUaaDNtM2MmgklHafjglhBysZ4d/fc+z5vzrnwn0o42Lycfe+4T3nJb2m4RJFTykm8ygk+fv3Gw7tDwnEQgIaDze7uLhevXKKnLwSAKOx/9c3aJJ3+6vT1dRwLOzJ4MbvmuM5FaBD3e6sCP6tQ+fSG27euHQtq+FuwzB1OlyPIAriln7hlkxPSLp4rIWZmZpzu7m5UVT0CPCQsLCw4kiTR09PDysoKHo8H0zTxeDy43e6afa/Xy8jIiFAXFI/Hnd7eXnRdR9M0LMsiHA7T2dmJbdvYto1hGORyORzHwbZtxsbGau9r1nZ2dhgeHkaSJHw+H+VyGVmWyWQymKZJtVrF6/XS3NzM3t4eqVSqfkaFQoHl5WWi0SiyLCPLMgB+vx9BEDAMg7W1NUqlEpq1iZ/W+qDBwUHC4TCrq6tYlkUkEiGbzZLNZjFNE1EUaW1tZWhoiHfFH1TWj7maoih0dXXR1tZGLpdjfn6eQqFAS0sLoVAI0zTRdZ1UKoVjKYiiXf9qc3NzTrFYJBaL4XK5KJfLLC0tkc/nSSaTKIpCe3s7/f39qKpKOp1mdHRUOASamppyfguZTIZoNMr4+DiLi4t8/lzCsjyo6mkGBi6yvr5OMpmkqakJgFgsZgSDQX/N2o07D2rkopbn2ZN7VKQurl5W2cxkKG7ofDl7no6B63QMXAdg8tF9tre3fbWMEokEiUTikOfHN9/iP7OJWX5F+EKV5dcbPH/6nb/LMIw/YU9OTp6Px+Mbuq7XFj4kSlSsEgBSg0Cl4tDY2IjP5zsCOxT21taWfnCgaZpvenoaTdNqWiAQYGJigkAgYBzcDQaD/rr0f6lfbq8VahbV+oAAAAAASUVORK5CYII=',
  'style/printjob.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHDh0ZgdWqhgAAAPZJREFUeJyt0jESgjAQBdAfh8KextbSnoPYJL2NuYONkgJmOIJHgE6OkFYmnR5BbkC5NojGEIyOW2122DebJcCfgj2SLMsopCGOY0gp2Xs9ej1sNuuP0OmkwTmnqqoszIIWi1XATBplWUIIYWGzgE4nhBAAAM75sI7o/aM8zz9CdV1Da42iKIaaAwHAbheDzkewZDsKzedAkiRWzXs1H+KL0YmI/EjbXrFcuvWJZTOk7PmHmfNyAqGUAftXhKYxL7QnQtrnRL188EujO2rbKwBA3i5DfpMX/zhjkFIKSqnJpiCo67qvEQdqmuYnxIKMMTDG/Az9Le5YqEcLs1eIzgAAAABJRU5ErkJggg==',
  'style/server.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHCyMCTL66/AAAAnJJREFUeJytlE1PE1EUhp9OP2lL27FtQGwA+QoQxEaIGIMLNyYujNGFP8Ef6MqFRIzRBEwwUA0oFFBKgDLSaad3mJnOzHWBJjSk3ejZ3Nx7c56c8543B/5TBDp9VCpH0jp38aWCXrOoN2wqFZ25OwPM3h6+ktf2UCqVZCAQolYTQJhqtU7T8fGTCfy0imEp1N994OmzeYrzU225ocsX15Usr39HuT6E2neNQnGSQjyC7cGpCXYT6q/ecqbpVzpoA3legMTsffRMDjcOugemCS0PDBvMFshQDKMuuoPCoSi9rsdPG5QAOB6EFHB9EC1oWCBzAwhhdAclEnGSWouzc3A9EA4oCvg+WC40bHCVHs60CnrNGMmovbt/c5XLoLHxQqB1dIImoCrguAnHxsVZFaCZINI3MBoCpBzpWBFAqjcBWo2TtEokCEEFPP9Pm7ZDrinQzxq4nvcSeNMRFIuF6dEcykGIBC+0uhsR9GsaYmOTo/1NEoV4d40AVDVO7IdPWEoe9VpMaMesvC6ztHXEUH6fmVuDLCwWyeXVF11BEslks8qTjEn9QNBoWGxtXWgaTRUYnxrh3oPiFWe3gXZ3T2UmE+T542FUNUitplMqGQwNJdG0Jvn+Psp7gr2dirw5VmiDtU3NNE1arRaqGsRxHDzPI5VSmJ7OE49fWOHc8vj0ceOKRm2geE+Mw8Nf6LqOYTSxLBfXdVHVMNlsjGQyxkH5G3XdQK8Zncc/MtoXWF76LNdWt8n3ZwmGwti2j+NAXzbKzvoq6VSE0YlBLpsROqyRtZUvcuX9Bl9L2xgNgZSSaDTM5MwoC4tFFh/OdVw//xy/AbreHTutN5hZAAAAAElFTkSuQmCC',
  'style/up.png' => 'iVBORw0KGgoAAAANSUhEUgAAAAsAAAALCAYAAACprHcmAAAABmJLR0QA6wDqANsTMxObAAAACXBIWXMAAAsQAAALEAGtI711AAAAB3RJTUUH1AUHCyYmDcqqaAAAADhJREFUeJxjYKAUrFkx8z82cUZ8CkMi0hlxKsZmIrIGRnwK0TUwElKIrAHZZHwKEc7ApxBdwyAAAB3iGPP2uU9fAAAAAElFTkSuQmCC',
  'style/view.thtml' => 'CiAgPHNjcmlwdCBsYW5ndWFnZT0iSmF2YVNjcmlwdCI+CiAgICBmdW5jdGlvbiBzZWxfYWxsKG1hc3Rlcl9zZWxlY3QpIHsKICAgICAgd2l0aCAoZG9jdW1lbnQuZF9mb3JtKSB7CiAgICAgICAgZm9yIChpPTA7IGk8ZWxlbWVudHMubGVuZ3RoOyBpKyspIHsKICAgICAgICAgIGVsZSA9IGVsZW1lbnRzW2ldOwogICAgICAgIGlmIChlbGUudHlwZT09ImNoZWNrYm94IikKICAgICAgICAgICAgZWxlLmNoZWNrZWQgPSBtYXN0ZXJfc2VsZWN0LmNoZWNrZWQ7CiAgICAgICAgfQogICAgICB9CiAgICB9CiAgPC9zY3JpcHQ+CgoKPGZvcm0gZW5jdHlwZT0ibXVsdGlwYXJ0L2Zvcm0tZGF0YSIgYWN0aW9uPSJ7YWN0aW9ufSIgbWV0aG9kPSJwb3N0IiBuYW1lPSJkX2Zvcm0iPgo8dGFibGUgY2VsbHBhZGRpbmc9IjAiIGNlbGxzcGFjaW5nPSIwIiBib3JkZXI9IjAiPgp7aGVhZGVyfQp7bGluZXN9CjwvdGFibGU+CjwvZm9ybT4KCg==',
  'style/workgroup.png' => 'iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH1AUHCyo0UsaULAAAAgJJREFUeJytlDtvUlEAgD/uBQryCGITgokoOQNpJC2LGEUmXzHpYDTqYOJQp7r3H7gZJ2P0B+jSwbi03ri4dKCaakxzF700ggYItxSlF8ujcB1IC+VlGj3bOec7X3LOlxz4T8MybnNped3sna/pR7h7AYQQA+ekcSJDksidDlNLRCmdj1KfcKAoCul02uxnreNETdlJbtdFtQyGBPXtBpqmYRjGADtWVGu1SRXBZwdr28RXqIw8MFa0kW3jthSQ7RLHmjuU028JnXBjs9kOJ7p/USaT2WB+fo5IJIIQgpmZBH6/f7RoWKHetwgEAly6PNfZ+6iztLxu9lbcFxmSRGXqJH6fi6oJdSXHyorC1FQMALfbPZRRlNdAT/69Qt/LkK10Cplmg0DpAa8eOjjq9QxlNE07eLVhhZ68eI732lcAXr6psXDu3siK+6L+Qr++vSMej/NZnkWSZB49niU1ouIB0SlXg6i9QqlYJZ/doVBNkUgkiMVieDweivrPAabY+IQQV7qiZ08XzZtXj7Nt1NG+/MZi1vD4bhAKTZBMJi17zO07033Mdfx+R1d05uw07z9k2dysdBatu+h6hq2tSfL5vBkMBi2jmFJpsltNiICQpBJOZwPTrLG6qtBq/cDr9SLL8trfGOj5RnRdX1RV9ZaqqjSbTYQQRKNRwuHwoZh/Hn8AelUdaSkrxgYAAAAASUVORK5CYII=',
);




# constructor
function smbwebclient ()
{
  $this->InitLanguage();
  if (isset($_GET['debug'])) {
    $_SESSION['DebugLevel'] = $_GET['debug'];
    unset($_GET['debug']);
  }
  if (isset($_GET['O'])) {
    $_SESSION['Order'] = $_GET['O'];
    unset($_GET['O']);
  }
  $this->debug = @$_SESSION['DebugLevel'];
  $this->order = @$_SESSION['Order'];

  # your base URL ends with '/' ? I think you are using mod_rewrite
  $this->cfgModRewrite = $this->cfgBaseUrl[strlen($this->cfgBaseUrl)-1] == '/' ? 'on' : 'off';
}

function Run ($path='')
{
  $path = stripslashes($path);
  $this->where = $path;

  if (isset($this->inlineFiles[$path])) {
    $this->DumpInlineFile($path);
    exit;
  }

  $this->Go(ereg_replace('/$','',ereg_replace('^/','',$this->cfgSambaRoot.'/'.$path)));

  $this->GetCachedAuth($this->PrintablePath());

  if (isset($_REQUEST['action']) AND method_exists($this, $_REQUEST['action'])) {
    $action = $_REQUEST['action'];
    $this->$action ();
  }

  switch ($this->Browse()) {
    case '': break;
    case 'ACCESS_DENIED':
    case 'LOGON_FAILURE':
      $this->CleanCachedAuth();
      header('Location: '.$this->GetUrl($path, 'auth', '1'));
      exit;
    default:
      $this->ErrorMessage($this->status);
      header('Location: '.$this->FromPath('..'));
      exit;
  }

  $this->View ();

}


function InitLanguage ()
{
  # default language
  $this->lang = $this->cfgDefaultLanguage;
  $langOK = false;

  # param from GET
  if (isset($_GET['lang']) AND isset($this->strings[$_GET['lang']])) {
    $this->lang = $_GET['lang'];
    $langOK = true;
  # current session
  } elseif (isset($_SESSION['Language']) AND isset($this->strings[$_SESSION['Language']])) {
    $this->lang = $_SESSION['Language'];
  } else {
    # take a look at HTTP_ACCEPT_LANGUAGE
    foreach (split(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $lang)
    foreach ($this->languages as $key => $filter)
    if (isset($this->strings[$key]) AND eregi('^('.$filter.')(;q=[0-9]\\.[0-9])?$', $lang)) {
      $this->lang = $key;
      $langOK = true;
    }
    # look at HTTP_USER_AGENT
    if (! $langOK) {
      reset($this->languages);
      foreach ($this->languages as $key => $filter)
      if (isset($this->strings[$key])
      AND eregi('(\(|\[|;[[:space:]])(' . $filter . ')(;|\]|\))', $_SERVER['HTTP_USER_AGENT'])) {
        $this->lang = $key;
        $langOK = true;
      }
    }
  }
  if ($langOK) $_SESSION['Language'] = $this->lang;
}

# returns a string in a given language
function _($str)
{
  # for english, all is done !
  if ($this->lang == 'en') return $str;

  # search string at english list (get position)
  $pos = array_search ($str, $this->strings['en']);
  if (($pos = array_search ($str, $this->strings['en'])) === FALSE)
    return $str;

  # found position at current language (ok!)
  if ($this->strings[$this->lang][$pos] <> '')
    return $this->strings[$this->lang][$pos];

  # found position at default language (better than nothing!)
  if ($this->strings[$this->cfgDefaultLanguage][$pos] <> '')
    return $this->strings[$this->cfgDefaultLanguage][$pos];

  # well, I cannot do anything better !
  return $str;
}

function DumpFile($file='', $name='', $isAttachment=0)
{
  if ($name == '') $name = basename($file);
  $pi = pathinfo(strtolower($name));
  $mimeType = @$this->mimeTypes[@$pi['extension']];
  if ($mimeType == '') $mimeType = 'application/octet-stream';

  # dot bug with IE
  if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
    $name = preg_replace('/\./','%2e', $name, substr_count($name, '.') - 1);
  }

  header('MIME-Version: 1.0');
  header("Content-Type: $mimeType; name =\"".htmlentities($name)."\"");
  if ($isAttachment)
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

function GetInlineFile ($file)
{
  if ($this->cfgInlineFiles == 'off') {
    # if does not exists, write from inline file ! (devel)
    if (! is_readable($file)) {
      $f = fopen($file, 'wb');
      fwrite($f, base64_decode($this->inlineFiles[$file]));
      fclose($f);
    }
    $f = fopen($file, 'r');
    $data = fread ($f, filesize($file));
    fclose ($f);
  } else {
    $data = base64_decode($this->inlineFiles[$file]);
  }
  return $data;
}

function DumpInlineFile ($file)
{
  $this->dumpFile('',$file);
  print $this->GetInlineFile ($file);
}

# debugging messages
function Debug ($message, $level=1)
{
  if ($level <= $this->debug) {
    error_log($message."\n", 3, $this->cfgLogFile);
    flush();
  }
}

# HTML page
function Page ($title='', $content='')
{
  if (@$_SESSION['ErrorMessage'] <> '') {
    $content .= "\n<script language=\"Javascript\">alert(\"{$_SESSION['ErrorMessage']}\")</script>\n";
    $_SESSION['ErrorMessage'] = '';
  }
  return $this->Template('style/page.thtml', array(
    '{title}' => $title,
    '{content}' => $content,
    '{style}' => $this->GetUrl('style/'),
    '{favicon}' => $this->GetUrl('style/favicon.ico')
    ));
}

# loads an HTML template
function Template ($file, $vars=array())
{
  return str_replace(array_keys($vars), array_values($vars), $this->GetInlineFile($file));
}

# HTML a href
function Link ($title, $url='', $name='')
{
  if ($name <> '') $name = "name = \"{$name}\"";
  return ($url == '') ? $title : "<a href=\"{$url}\" {$name}>{$title}</a>";
}

# HTML img
function Image ($url, $alt='', $extra='')
{
  return ($url == '') ? $title : "<img src=\"{$url}\" alt=\"{$alt}\" border=\"0\" {$extra} />";
}

# HTML select (combo)
function Select ($name, $value, $options)
{
  $html = "<select name=\"{$name}\">\n";
  foreach ($options as $key => $description) {
    $selected = ($key == $value) ? "selected" : "";
    $html .= "<option value=\"{$key}\" $selected>{$description}</option>\n";
  }
  $html .= "</select>\n";
  return $html;
}

# HTML check box
function CheckBox ($name, $value, $checked = false)
{
  return $this->Input($name, $value, 'checkbox', $checked ? "checked" : "");
}

function Input ($name, $value, $type = 'text', $extra='')
{
  return "<input type=\"{$type}\" name=\"{$name}\" value=\"".htmlentities($value)."\" {$extra}/>";
}

# basic auth
function GetAuth ($path="")
{
  if (@$_GET['auth'] == 1 OR ($this->cfgAnonymous == 'off' AND !isset($_SERVER['PHP_AUTH_USER']))) {
    $_SESSION['AuthSubmit'] = 'yes';
    $time = date("h:i:s");
    header("WWW-authenticate: basic realm=\"{$path} ($time)\"");
    header("HTTP/1.0 401 Unauthorized");
    $this->Page('unauthorized');
    exit;
  }
  $this->user = @$_SERVER['PHP_AUTH_USER'];
  $this->pw = @$_SERVER['PHP_AUTH_PW'];
}

# return an URL (adding a param)
function GetUrl ($path='', $arg='', $val='')
{
  $get = $_GET;

  # delete switches from URL
  $get['debug'] = '';
  $get['lang'] = '';
  $get['auth'] = '';

  $get['path'] = $path;
  if ($arg <> '') {
    if (! is_array($arg)) $get[$arg] = $val;
    else foreach ($arg as $key=>$value) $get[$key] = $value;
  }

  # build query string
  $query = array();
  foreach ($get as $key=>$value) if ($value <> '') {
    if ($this->cfgModRewrite <> 'on' OR $key <> 'path')
      $query[] = urlencode($key).'='.urlencode($value);
  }
  if (($query = join('&',$query)) <> '') $query = '?'.$query;

  if ($this->cfgModRewrite == 'on') {
    return $this->cfgBaseUrl.str_replace('%2F','/',urlencode($get['path'])).$query;
  } else {
    return $this->cfgBaseUrl.$query;
  }
}

function GetMicroTime ()
{
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

function ErrorMessage ($msg)
{
  $_SESSION['ErrorMessage'] = @$_SESSION['ErrorMessage'] . $msg;
}


function CleanCachedAuth ()
{
  $mode = $this->type;
  @$_SESSION['CachedAuth'][$mode][$this->$mode] = '';
}

function GetCachedAuth ($path)
{
  $this->user = $this->pw = '';
  $nextLevel = array('network'=>'','workgroup'=>'network','server'=>'workgroup','share'=>'server');
  if (@$_SESSION['AuthSubmit'] == 'yes') {
    # store auth in cache
    $_SESSION['AuthSubmit'] = 'no';
    $mode = $this->type;
    $this->user = @$_SERVER['PHP_AUTH_USER'];
    $this->pw = @$_SERVER['PHP_AUTH_PW'];
    $_SESSION['CachedAuth'][$mode][$this->$mode]['User'] = $this->user;
    $_SESSION['CachedAuth'][$mode][$this->$mode]['Password'] = $this->pw;
    for ($mode = $nextLevel[$mode]; $mode <> ''; $mode = $nextLevel[$mode]) {
      if (! isset($_SESSION['CachedAuth'][$mode][$this->$mode])) {
        $_SESSION['CachedAuth'][$mode][$this->$mode]['User'] = $this->user;
        $_SESSION['CachedAuth'][$mode][$this->$mode]['Password'] = $this->pw;
      }
    }
  } elseif (@$_GET['auth'] <> 1) {
    # get auth from cache
    for ($mode = $this->type; $mode <> ''; $mode = $nextLevel[$mode]) {
      if (isset($_SESSION['CachedAuth'][$mode][$this->$mode])) {
        $this->user = $_SESSION['CachedAuth'][$mode][$this->$mode]['User'];
        $this->pw = $_SESSION['CachedAuth'][$mode][$this->$mode]['Password'];
        break;
      }
    }
    if ($this->user == '') $this->GetAuth($path);
  } else $this->GetAuth($path);
}

function View ()
{
  $selected = (is_array(@$_POST['selected'])) ? $_POST['selected'] : array(); 
  switch ($this->type) {
    case 'file':
      $this->DumpFile ($this->tempFile, $this->name);
      exit;
    case 'network':
    case 'workgroup':
    case 'server':
      $headers = array ('Name' => 'N', 'Comments' => 'C');
      break;
    case 'printer':
      $headers = array ('Name' => 'N', 'Size' => 'S');
      break;
    default:
      $headers = array ('Name' => 'N', 'Size' => 'S', 'Type' => 'T', 'Modified' => 'D');
  }

  $header = '<tr>';
  $header .= '<th class="checkbox"><input type="checkbox" name="chkall" onclick="javascript:sel_all(this)" /></th>';

  $icons = array ('A' => 'up', 'D' => 'down');
  foreach ($headers as $title => $order) {
    if ($this->order[0] == $order) {
      $ad = ($this->order[1] == 'A') ? 'D' : 'A';
      $icon = $this->Icon($icons[$ad]);
      $style[$title] = 'class="order-by"';
    } else {
      $ad = 'A';
      $icon = '';
      $style[$title] = '';
    }
    $url = $this->GetUrl($this->where, 'O', $order.$ad);
    $header .= "<th>".$this->Link($this->_($title), $url).' '.$icon."</th>";
  }
  $lang = strtoupper($this->lang);
  $time = date("H:i");
  $logout = $this->Icon('logout', $this->GetUrl($this->where, 'auth', '1'));
  $header .= "<th width=\"100%\">&nbsp;</th><th class=\"language\">{$lang}</th><th class=\"toolbar\">{$logout}&nbsp;&nbsp;{$time}</th>";

  $lines = $this->ViewForm ($this->type, $style, $headers);
  foreach ($this->results as $file => $data) {
    if ($data['type']=='file' OR $data['type']=='printjob') {
      $size = $this->PrintableBytes($data['size']);
      $pi = pathinfo(strtolower($file));
      if (@$this->mimeTypes[$pi['extension']] <> '')
        $type = sprintf($this->_("File %s"), strtoupper($pi['extension']));
      else
        $type = '';
    } else {
      $size = '';
      $type = $this->_("File Folder");
    }
    $modified = date($this->_("m/d/Y h:i"), @$data['time']);
    $check = $this->CheckBox("selected[]", ($data['type'] <> 'printjob') ? $file : $data['id'], in_array($file, $selected));
    $icon = $this->Icon($data['type']);
    $comment = @$data['comment'];
    if ($data['type'] <> 'printjob') {
      $file = $this->Link(htmlentities($file), $this->FromPath($file));
    }
    $lines .= "<tr>".
      "<td class=\"checkbox\">{$check}</td>".
      "<td {$style['Name']}>{$icon} {$file}</td>".
      (isset($headers['Size']) ? "<td {$style['Size']} align=\"right\">{$size}</td>" : "").
      (isset($headers['Type']) ? "<td {$style['Type']}>{$type}</td>" : "").
      (isset($headers['Comments']) ? "<td {$style['Comments']}>{$comment}</td>" : "").
      (isset($headers['Modified']) ? "<td {$style['Modified']}>{$modified}</td>" : "").
      "<td width=\"100%\" colspan=\"3\">&nbsp;</td>".
      "</tr>\n";
  }

  $macros['{action}'] = $this->GetUrl($this->where);
  $macros['{ok}'] = $this->_("Ok");
  $macros['{header}'] = $header;
  $macros['{lines}'] = $lines;

  print $this->Page($this->name, $this->Template("style/view.thtml", $macros));
}

function ViewForm ($type, $style, $headers)
{
  $icon = $this->Icon('dotdot');
  $amenu = array();
  $html = $widget = '';
  if ($this->where <> '') $amenu['UpAction'] = $this->_("Up");
  switch ($type) {
    case 'network':
    case 'server':
      break;
    case 'printer':
      switch (@$_REQUEST['action']) {
        case 'PrintFileInput':
          $amenu = array();
          $icon = $this->Icon('file');
          $widget = $this->Input("action", "PrintFileAction", "hidden").
                    $this->Input("file","", "file").
                    $this->Input('ok', $this->_("Ok"), 'submit');
          break;
        default:
          $amenu['PrintFileInput'] = $this->_("Print a file");
          $amenu['CancelSelectedAction'] = $this->_("Cancel selected jobs");
      }
      break;
    case 'share':
      switch (@$_REQUEST['action']) {
        case 'NewFolderInput':
          $amenu = array();
          $icon = $this->Icon('folder');
          $widget = $this->Input("action", "NewFolderAction", "hidden").
                    $this->Input("folder","").
                    $this->Input('ok', $this->_("Ok"), 'submit');
          break;
        case 'NewFileInput':
          $amenu = array();
          $icon = $this->Icon('file');
          $widget = $this->Input("action", "NewFileAction", "hidden").
                    $this->Input("file","", "file").
                    $this->Input('ok', $this->_("Ok"), 'submit');
          break;
        default:
          $amenu['NewFolderInput'] = $this->_("New folder");
          $amenu['NewFileInput'] = $this->_("New file (upload)");
          $amenu['DeleteSelectedAction'] = $this->_("Delete selected items");
          # $amenu['DownloadArchiveAction'] = $this->_("Get a ZIP file with selected items");
      }
      break;
    case 'workgroup':
      if (@$_REQUEST['action'] == 'SendMessageInput') {
        $amenu = array();
        $icon = $this->Icon('file');
        $widget = $this->Input("action", "SendMessageAction", "hidden").
                  $this->Input("message","").
                  $this->Input('ok', $this->_("Ok"), 'submit');
      } else {
        $amenu['SendMessageInput'] = $this->_("Send a popup message");
      }
      break;
    default: print $type;
  }
  if (count($amenu)) {
    $widget = $this->Select("action", "", $amenu) . $this->Input('ok', $this->_("Ok"), 'submit');
  }
  if ($widget <> '') {
    $html = "<tr>".
    "<td>&nbsp;</td>".
    "<td {$style['Name']}>{$icon} {$widget}</td>".
    (isset($headers['Size']) ? "<td {$style['Size']} align=\"right\">&nbsp;</td>" : "").
    (isset($headers['Type']) ? "<td {$style['Type']}>&nbsp;</td>" : "").
    (isset($headers['Comments']) ? "<td {$style['Comments']}>&nbsp;</td>" : "").
    (isset($headers['Modified']) ? "<td {$style['Modified']}>&nbsp;</td>" : "").
    "<td width=\"100%\" colspan=\"3\">&nbsp;</td>".
    "</tr>\n";
  }
  return $html;
}

function UpAction ()
{
  header('Location: '.$this->FromPath('..'));
  exit;
}

function SendMessageAction ()
{
  if (trim($_POST['message']) <> '' AND is_array($_POST['selected'])) {
    foreach ($_POST['selected'] as $server)
      $this->SendMessage($server, $_POST['message']);
  }
  if ($this->status <> '') {
    $this->ErrorMessage($this->status);
  }
  header('Location: '.$this->FromPath('.'));
  exit;
}

function NewFolderAction ()
{
  if (trim($_POST['folder']) <> '') {
    $this->parent = $this->path;
    $this->name = $_POST['folder'];
    $this->MakeDirectory();
  }
  if ($this->status <> '') {
    $this->ErrorMessage($this->status);
  }
  header('Location: '.$this->FromPath('.'));
  exit;
}

function NewFileAction ()
{
  if ($_FILES['file']['tmp_name'] <> '') {
    $this->parent = $this->path;
    $this->name = $_FILES['file']['name'];
    $this->UploadFile($_FILES['file']['tmp_name']);
  }
  if ($this->status <> '') {
    $this->ErrorMessage($this->status);
  }
  header('Location: '.$this->FromPath('.'));
  exit;
}

function DeleteSelectedAction ()
{
  $status = '';
  if (is_array(@$_POST['selected'])) {
    $base = $this->fullPath;
    foreach ($_POST['selected'] as $file) {
      $this->Go($base.'/'.$file);
      $this->Remove();
      if ($this->status <> '') $status = $this->status;
    }
    $this->Go($base);
    if ($status <> '') {
      $this->ErrorMessage($status);
    }
  }
  header('Location: '.$this->FromPath('.'));
  exit;
}

function PrintFileAction ()
{
  if ($_FILES['file']['tmp_name'] <> '') {
    $this->PrintFile($_FILES['file']['tmp_name']);
  }
  if ($this->status <> '') {
    $this->ErrorMessage($this->status);
  }
  header('Location: '.$this->FromPath('.'));
  exit;
}

function CancelSelectedAction ()
{
  $status = '';
  if (is_array($_POST['selected'])) {
    foreach ($_POST['selected'] as $job) {
      $this->CancelPrintJob($job);
      if ($this->status <> '') $status = $this->status;
    }
  }
  if ($status <> '') {
    $this->ErrorMessage($status);
  }
  header('Location: '.$this->FromPath('.'));
  exit;
}

/*
function DownloadArchiveAction ()
{
  $status = '';
  $this->debug = true;
  if (is_array($_POST['selected'])) {
    $base = $this->fullPath;
    foreach ($_POST['selected'] as $file) {
      $this->Go($base.'/'.$file);
      $this->Backup();
      $tarfiles[] = $this->tempFile;
      if ($this->status <> '') $status = $this->status;
    }
  }
  $this->Go($base);
  if ($status <> '') {
    $this->ErrorMessage($status);
  }
  // header('Location: '.$this->FromPath('.'));
  print_r($tarfiles);  
  exit;
}
*/

# print KB
function PrintableBytes ($bytes)
{
  if ($bytes < 1024)
    return "0 KB";
  elseif ($bytes < 10*1024*1024)
    return number_format($bytes / 1024,0) . " KB";
  elseif ($bytes < 1024*1024*1024)
    return number_format($bytes / (1024 * 1024),0) . " MB";
  else
    return number_format($bytes / (1024*1024*1024),0) . " GB";
}

function PrintablePath ()
{
  switch ($this->type) {
    case 'network':    return $this->_("Windows Network");
    case 'workgroup':  return $this->workgroup;
    case 'server':     return '\\\\'.$this->server;
    case 'share':
      $pp = '\\\\'.$this->server.'\\'.$this->share;
      if ($this->where <> '') {
        $pp .= '\\'.str_replace('/','\\',$this->where);
      }
      return $pp;
  }
}

function Icon ($icon, $url='')
{
  $image = $this->Image($this->GetUrl("style/{$icon}.png"),'','align="absmiddle"');
  return ($url <> '') ? $this->Link($image, $url) : $image;
}

# builds a new path from current and a relative path
function FromPath ($relative='')
{
  switch ($relative) {
    case '.':
    case '':    $path = $this->where; break;
    case '..':  $path = samba::_DirectoryName($this->where); break;
    default:    $path = ereg_replace('^/', '', $this->where.'/'.$relative);
  }
  return $this->GetUrl($path);
}

}

# -----------------------------------------------------------------
# Class: samba
#
# Description:
#   Interface to SAMBA trought smbclient command
#

class samba {

var $cfgSmbClient = 'smbclient';
var $user='', $pw='', $cfgAuthMode='';
var $cfgDefaultServer='localhost', $cfgDefaultUser='', $cfgDefaultPassword='';
var $types = array ('network', 'workgroup', 'server', 'share');
var $type = 'network';
var $network = 'Windows Network';
var $workgroup='', $server='', $share='', $path='';
var $name = '';
var $workgroups=array(), $servers=array(), $shares=array(), $files=array();
var $cfgCachePath = '';
var $cached = false;
var $tempFile = '';
var $debug = false;
var $socketOptions = 'TCP_NODELAY IPTOS_LOWDELAY SO_KEEPALIVE SO_RCVBUF=8192 SO_SNDBUF=8192';
var $blockSize = 1200;
var $order = 'NA';
var $status = '';
var $parser = array(
"^added interface ip=([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}) bcast=([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}) nmask=([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\$" => 'SKIP',
"Anonymous login successful" => 'SKIP',
"^Domain=\[(.*)\] OS=\[(.*)\] Server=\[(.*)\]\$" => 'SKIP',
"^\tSharename[ ]+Type[ ]+Comment\$" => 'SHARES_MODE',
"^\t---------[ ]+----[ ]+-------\$" => 'SKIP',
"^\tServer   [ ]+Comment\$" => 'SERVERS_MODE',
"^\t---------[ ]+-------\$" => 'SKIP',
"^\tWorkgroup[ ]+Master\$" => 'WORKGROUPS_MODE',
"^\t(.*)[ ]+(Disk|IPC)[ ]+IPC.*\$" => 'SKIP',
"^\tIPC\\\$(.*)[ ]+IPC" => 'SKIP',
"^\t(.*)[ ]+(Disk|Printer)[ ]+(.*)\$" => 'SHARES',
'([0-9]+) blocks of size ([0-9]+)\. ([0-9]+) blocks available' => 'SIZE',
"Got a positive name query response from ([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})" => 'SKIP',
"^session setup failed: (.*)\$" => 'LOGON_FAILURE',
'^tree connect failed: ERRSRV - ERRbadpw' => 'LOGON_FAILURE',
"^Error returning browse list: (.*)\$" => 'ERROR',
"^tree connect failed: (.*)\$" => 'ERROR',
"^Connection to .* failed\$" => 'CONNECTION_FAILED',
'^NT_STATUS_INVALID_PARAMETER' => 'INVALID_PARAMETER',
'^NT_STATUS_DIRECTORY_NOT_EMPTY removing' => 'DIRECTORY_NOT_EMPTY',
'ERRDOS - ERRbadpath \(Directory invalid.\)' => 'NOT_A_DIRECTORY',
'NT_STATUS_NOT_A_DIRECTORY' => 'NOT_A_DIRECTORY',
'^NT_STATUS_NO_SUCH_FILE listing ' => 'NO_SUCH_FILE',
'^NT_STATUS_ACCESS_DENIED' => 'ACCESS_DENIED',
'^cd (.*): NT_STATUS_OBJECT_PATH_NOT_FOUND' => 'OBJECT_PATH_NOT_FOUND',
'^cd (.*): NT_STATUS_OBJECT_NAME_NOT_FOUND' => 'OBJECT_NAME_NOT_FOUND',
"^\t(.*)\$" => 'SERVERS_OR_WORKGROUPS',
"^([0-9]+)[ ]+([0-9]+)[ ]+(.*)\$" => 'PRINT_JOBS',
"^Job ([0-9]+) cancelled" => 'JOB_CANCELLED',
'^[ ]+(.*)[ ]+([0-9]+)[ ]+(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[ ](Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ ]+([0-9]+)[ ]+([0-9]{2}:[0-9]{2}:[0-9]{2})[ ]([0-9]{4})$' => 'FILES',
"^message start: ERRSRV - ERRmsgoff" => 'NOT_RECEIVING_MESSAGES',
"^NT_STATUS_CANNOT_DELETE" => 'CANNOT_DELETE'
);

function samba ($path='')
{
  if ($path <> '') $this->Go ($path);
}

# path: WORKGROUP/SERVER/SHARE/PATH
function Go ($path = '')
{
  $a = ($path <> '') ? split('/',$path) : array();
  for ($i=0, $ap=array(); $i<count($a); $i++)
  switch ($i) {
    case 0: $this->workgroup = $a[$i]; break;
    case 1: $this->server = $a[$i]; break;
    case 2: $this->share = $a[$i]; break;
    default: $ap[] = $a[$i];
  }
  $this->path = join('/', $ap);
  $this->type = $this->types[(count($a) > 3) ? 3 : count($a)];
  $this->name = basename($path);
  $this->parent = samba::_DirectoryName($this->path);
  $this->fullPath = $path;
}

function Browse ($order='NA')
{
  $this->results = array();
  $this->shares = $this->servers = $this->workgroups = $this->files = $this->printjobs = array();
  $server = ($this->server == '') ? $this->cfgDefaultServer : $this->server;
  # smbclient call
  switch ($this->type) {
    case 'share':
      $this->_SmbClient('dir', $this->path);
      switch ($this->status) {
        case 'NO_SUCH_FILE':
          $this->_SmbClient('queue', $this->path);
          $this->type = 'printer';
          break;
        case 'NOT_A_DIRECTORY': $this->_Get ();
      }
      break;
    case 'workgroup':
      if (($server = $this->_MasterOf($this->workgroup)) == $this->cfgDefaultServer) break;
    default:
      $this->_SmbClient('', $server);
  }
  # sort and select results
  $results = array (
    'network' => 'workgroups', 'workgroup' => 'servers',
    'server' => 'shares', 'share' => 'files', 'folder' => 'files',
    'printer' => 'printjobs'
  );
  if (isset($results[$this->type])) {
    $this->results = $this->$results[$this->type];
    # we need a global var for the compare function
    $GLOBALS['SMB_SORT_BY'] = ($this->order <> '') ? $this->order : 'NA';
    uasort($this->results, array('samba', '_GreaterThan'));
  }
  return $this->status;
}

function Remove ()
{
  $this->_SmbClient('del "'.$this->name.'"', $this->parent);
  if ($this->status == 'NO_SUCH_FILE') {
    # it is a folder or not exists
    $this->_SmbClient('dir', $this->parent);
    # OK : if it is a folder, delete recursively
    if (@$this->files[$this->name]['type'] == 'folder') {
      $this->_DeleteFolder ();
    }
  }
}

# recursive deletion of SMB folders
function _DeleteFolder ()
{
  $this->_SmbClient('rmdir "'.basename($this->path).'"', samba::_DirectoryName($this->path));
  if ($this->status == 'DIRECTORY_NOT_EMPTY') {
    $this->files = array();
    $savedPath = $this->path;
    $this->_SmbClient('dir', $this->path);
    $files = $this->files;
    foreach ($files as $name => $info) {
      switch ($info['type']) { 
        case 'folder':
          $this->path = $savedPath.'/'.$name;
          $this->_DeleteFolder();
          break;
        case 'file':
          $this->_SmbClient('del "'.$name.'"', $this->path);
      }
    }
    $this->path = $savedPath;
    $this->_SmbClient('rmdir "'.basename($this->path).'"', samba::_DirectoryName($this->path));
  }
}

function MakeDirectory ()
{
  $this->_SmbClient('mkdir "'.$this->name.'"', $this->parent);
}

function UploadFile ($file)
{
  $this->_SmbClient('put "'.$file.'" "'.$this->name.'"', $this->parent);
}

function PrintFile ($file)
{
  $this->_SmbClient('print '.$file);
}

function CancelPrintJob ($job)
{
  $this->_SmbClient('cancel '.$job);
}

function Backup ($zipfile='')
{
  $this->tempFile = tempnam('/tmp','swc').'.tar';
  $this->_SmbClient('tar c '.$this->tempFile, $this->path);
  // if ($this->status == '') $this->_Tar2Zip ($zipfile);
}

function _Tar2Zip ($zipfile)
{
  $tempDir = '/tmp/t2z.'.time();
  $tempZip = $tempDir.'.zip';
  mkdir ($tempDir);
  system("tar -C $tempDir -x -f {$this->tempFile}");
  system("cd {$tempDir}; zip -r {$tempZip} *");
  system("rm -r {$tempDir}; mv {$tempZip} {$zipfile}");
}

function Restore ($zipfile='')
{
  $this->_Zip2Tar ($zipfile);
  $this->_SmbClient('tar x '.$this->tempFile, $this->path);
}

function _Zip2Tar ($zipfile)
{
  $tempDir = '/tmp/t2z.'.time();
  $this->tempFile = tempnam('/tmp','swc').'.tar';
  mkdir ($tempDir);
  system("unzip -d {$tempDir} {$zipfile}");
  system("tar -C $tempDir -c -f {$this->tempFile} *");
  system("rm -rf {$tempDir}");
}

function _GreaterThan ($a, $b)
{
  global $SMB_SORT_BY;
  list ($yes, $no) = ($SMB_SORT_BY[1] == 'D') ? array(-1,1) : array (1,-1);
  if ($a['type'] <> $b['type']) {
    return ($a['type'] == 'file') ? $yes : $no;
  } else {
    switch ($SMB_SORT_BY[0]) {
      case 'N': return (strtolower($a['name']) > strtolower($b['name'])) ? $yes : $no;
      case 'D': return (@$a['time'] > @$b['time']) ? $yes : $no;
      case 'S': return (@$a['size'] > @$b['size']) ? $yes : $no;
      case 'C': return (strtolower(@$a['comment']) > strtolower(@$b['comment'])) ? $yes : $no;
      case 'T': 
        $pia = pathinfo(strtolower($a['name']));
        $pib = pathinfo(strtolower($b['name']));
        return (@$pia['extension'] > @$pib['extension']) ? $yes : $no;
    }
  }
}

function _MasterOf ($workgroup)
{
  $saved = array ($this->type, $this->user, $this->pw);
  if ($this->cfgDefaultUser <> '') {
    list ($this->user, $this->pw) = array ($this->cfgDefaultUser, $this->cfgDefaultPassword);
  }
  $this->type = 'network';
  $this->_SmbClient('', $this->cfgDefaultServer);
  list ($this->type, $this->user, $this->pw) = $saved;
  return $this->workgroups[$this->workgroup]['comment'];
}

# get a file (including a cache)
function _Get ()
{
  $this->_SmbClient('dir "'.$this->name.'"', $this->parent);
  if ($this->status == '') {
    $this->type = 'file';
    $this->size = $this->files[$this->name]['size'];
    $this->time = $this->files[$this->name]['time'];
    if ($this->cfgCachePath == '') {
      $this->tempFile = tempnam('/tmp','swc');
      $getFile = true;
    } else {
      $this->tempFile = $this->cfgCachePath . $this->fullPath;
      $getFile = filemtime($this->tempFile) < $this->time OR !file_exists($this->tempFile);
      if ($getFile AND ! is_dir(samba::_DirectoryName($this->tempFile)))
        samba::_MakeDirectoryRecursively(samba::_DirectoryName($this->tempFile));
    }
    if ($getFile) $this->_SmbClient('get "'.$this->name.'" "'.$this->tempFile.'"', $this->parent);
    $this->cached = ! $getFile;
  }
}

function SendMessage ($server, $message)
{
  $this->_SmbClient ('message', $server, $message);
}

function _SmbClient ($command='', $path='', $message='')
{
  $this->status = '';
  if ($command == '') {
    $smbcmd = "-L ".escapeshellarg($path);
  } elseif ($command == 'message') {
    $smbcmd = "-M ".escapeshellarg($path);
  } else {
    $smbcmd = escapeshellarg("//{$this->server}/{$this->share}").
    " -c ".escapeshellarg($command);
    if ($path <> '') $smbcmd .= ' -D '.escapeshellarg($path);
  }
  $options = '';
  if ($command <> '') {
    if ($this->workgroup <> '') $options .= ' -W '.escapeshellarg($this->workgroup);
    if ($this->socketOptions <> '') $options .= ' -O '.escapeshellarg($this->socketOptions);
    if ($this->blockSize <> '') $options .= ' -b '.$this->blockSize;
  }
  if ($this->user <> '') {
    # not anonymous
    switch ($this->cfgAuthMode) {
      case 'SMB_AUTH_ENV': putenv('USER='.$this->user.'%'.$this->pw); break;
      case 'SMB_AUTH_ARG': $smbcmd .= ' -U '.escapeshellarg($this->user.'%'.$this->pw);
    }
  }
  $cmdline = $this->cfgSmbClient.$options.' -N '.$smbcmd." 2>&1";
  if ($message <> '') $cmdline = "echo ".escapeshellarg($message).' | '.$cmdline;
  return $this->_ParseSmbClient ($cmdline, $command, $path);
}

function _ParseSmbClient ($cmdline)
{
  $output = `{$cmdline}`;
  if ($this->debug) {
    print "<pre>\n";
    print "Command: {$cmdline}\n\n[smbclient]\n{$output}\n[/smbclient]\n";
    print "</pre>\n";
  }
  $lineType = '';
  foreach (split("\n", $output) as $line) if ($line <> '') {
    $regs = array();
    reset ($this->parser);
    $linetype = 'skip';
    $regs = array();
    foreach ($this->parser as $regexp => $type) {
      # preg_match is much faster than ereg (Bram Daams)
      if (preg_match('/'.$regexp.'/', $line, $regs)) {
        $lineType = $type;
        break;
      }
    }
    switch ($lineType) {
      case 'SKIP': continue;
      case 'SHARES_MODE': $mode = 'shares'; break;
      case 'SERVERS_MODE': $mode = 'servers'; break;
      case 'WORKGROUPS_MODE': $mode = 'workgroups'; break;
      case 'SHARES':
        $name = trim($regs[1]);
        $this->shares[$name] = array (
          'name' => $name,
          'type' => strtolower($regs[2]),
          'comment' => $regs[3]
        );
        break;
      case 'SERVERS_OR_WORKGROUPS':
        $name = trim(substr($line,1,21));
        $comment = trim(substr($line, 22));
        if ($mode == 'servers') {
          $this->servers[$name] = array ('name' => $name, 'type' => 'server', 'comment' => $comment);
        } else {
          $this->workgroups[$name] = array ('name' => $name, 'type' => 'workgroup', 'comment' => $comment);
        }
        break;
      case 'FILES':
        # with attribute ?
        if (preg_match("/^(.*)[ ]+([D|A|H|S|R]+)$/", trim($regs[1]), $regs2)) {
          $attr = trim($regs2[2]);
          $name = trim($regs2[1]);
        } else {
          $attr = '';
          $name = trim($regs[1]);
        }
        if ($name <> '.' AND $name <> '..') {
          $type = (strpos($attr,'D') === false) ? 'file' : 'folder';
          $this->files[$name] = array (
            'name' => $name,
            'attr' => $attr,
            'size' => $regs[2],
            'time' => samba::_ParseTime($regs[4],$regs[5],$regs[7],$regs[6]),
            'type' => $type
          );
        }
        break;
      case 'PRINT_JOBS':
        $name = $regs[1].' '.$regs[3];
        $this->printjobs[$name] = array(
          'name'=>$name,
          'type'=>'printjob',
          'id'=>$regs[1],
          'size'=>$regs[2]
        );
        break;
      case 'SIZE':
        $this->size = $regs[1] * $regs[2];
        $this->available = $regs[3] * $regs[2];
        break;
      case 'ERROR': $this->status = $regs[1]; break;
      default:  $this->status = $lineType;
    }
  }
}

# returns unix time from smbclient output
function _ParseTime ($m, $d, $y, $hhiiss)
{
  $his= split(':', $hhiiss);
  $im = 1 + strpos("JanFebMarAprMayJunJulAgoSepOctNovDec", $m) / 3;
  return mktime($his[0], $his[1], $his[2], $im, $d, $y);
}

# make a directory recursively
function _MakeDirectoryRecursively ($path, $mode = 0777)
{
  if (strlen($path) == 0) return 0;
  if (is_dir($path)) return 1;
  elseif (samba::_DirectoryName($path) == $path) return 1;
  return (samba::_MakeDirectoryRecursively(samba::_DirectoryName($path), $mode)
    and mkdir($path, $mode));
}

# I do not like PHP dirname
function _DirectoryName ($path='')
{
  $a = split('/', $path);
  $n = (trim($a[count($a)-1]) == '') ? (count($a)-2) : (count($a)-1);
  for ($dir=array(),$i=0; $i<$n; $i++) $dir[] = $a[$i];
  return join('/',$dir);
}

}

# creates a new smbwebclient object and go

set_time_limit(1200);
# error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL);

session_name('SMBWebClientID');
session_start();

$swc = new smbwebclient;
$swc->Run(@$_REQUEST['path']);

?>