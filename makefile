all: smbwebclient.php

smbwebclient.php: php/*
	php4 -f makefile.php smbwebclient.php > smbwebclient.php

style: php/style.php

strings: php/strings.php

translators: php/translators.php

php/style.php: style/*
	php4 -f makefile.php style > php/style.php

php/strings.php:
	php4 -f makefile.php strings > php/strings.php

php/translators.php:
	php4 -f makefile.php translators > php/translators.php
