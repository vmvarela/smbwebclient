<?php
#
# Supported languages
#
$languages = array(
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

# --------------------------------------------------------------------
# Function: GetString
# Description: Returns a string in a given language
#
function GetString ($str) {
  global $strings;

  if ($_SESSION['Language'] == 'en') return $str;
  $pos = array_search ($str, $strings['en']);
  if (($pos = array_search ($str, $strings['en'])) === FALSE)
    return $str;
  if ($strings[$_SESSION['Language']][$pos] <> '')
    return $strings[$_SESSION['Language']][$pos];
  if ($strings[cfgDefaultLanguage][$pos] <> '')
    return $strings[cfgDefaultLanguage][$pos];
  return $str;
}


# --------------------------------------------------------------------
# Function: InitLanguage
# Description: Initializes language environment
#
function InitLanguage () {
  global $strings, $languages;

  # language setup
  if (isset($_GET['lang']) AND isset($strings[$_GET['lang']]))
    $_SESSION['Language'] = $_GET['lang'];

  # take a look at HTTP_ACCEPT_LANGUAGE
  if (! isset($_SESSION['Language'])) {
    $accepted_languages = split(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    foreach ($accepted_languages as $lang)
      foreach ($languages as $key => $filter)
        if (eregi('^('.$filter.')(;q=[0-9]\\.[0-9])?$', $lang)) {
          $_SESSION['Language'] = $key;
          break 2;
        }  
  }

  # look at HTTP_USER_AGENT
  if (! isset($_SESSION['Language'])) {
    reset($languages);
    foreach ($languages as $key => $filter)
      if (eregi('(\(|\[|;[[:space:]])(' . $filter . ')(;|\]|\))',
        $_SERVER['HTTP_USER_AGENT'])) {
        $_SESSION['Language'] = $key;
        break;
      }
  }

  # default language
  if ((! isset($_SESSION['Language'])) OR ! is_array($strings[$_SESSION['Language']]))
    $_SESSION['Language'] = cfgDefaultLanguage;
}

?>