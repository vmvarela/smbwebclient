all: php/style.php smbwebclient.php

smbwebclient.php: php/*
	php4 -f makefile.php smbwebclient.php > smbwebclient.php

php/style.php: style/*
	php4 -f makefile.php style > php/style.php

strings:
	php4 -f makefile.php strings > php/strings.php

translators:
	php4 -f makefile.php translators > php/translators.php
