<?php

# This is the configuration section. It contains PHP contants that
# give this script its instructions.


# You can set this constant to see a single domain (or workgroup),
# a domain and a server, a domain/server/shared resource
# and (even) a path into a domain/server/shared.
# For example to see only folder Bilbo in 'Bolson' shared resource
# at 'HOBBIT' server in 'TIERRAMEDIA' domain would be:
# define ('cfgSambaRoot', 'TIERRAMEDIA/HOBBIT/Bolson/Bilbo');
# Note: Do not put any slash at beginning/end.

@define ('cfgSambaRoot', '');


# Anonymoys login is disallowed by default.
# If you have public shares in your network, turn on this flag
# i.e. define ('cfgAnonymous', 'on');

@define ('cfgAnonymous', 'off');


# Path at web server to store downloaded files. This script will
# check when it need to update the cached file. This path must be
# writable to the user that runs your web server.
# If you set this value to '' cache will be disabled.
# Note: this feature is a security risk.

@define ('cfgCachePath', '');


# This script try to set language from web browser. If browser
# language is not supported you can set a default language.

@define ('cfgDefaultLanguage', 'en');
 

# Access logfile (apache compatible). You can set to '' to
# disable logging. This file must be writable to the user
# that runs your web server.

@define ('cfgLogFile', '');


# Default browse server for your network. A browse server is where
# you run smbclient -L subcommand to read available domains and/or
# workgroups. Set to 'localhost' if you are running SAMBA server
# in your web server.

@define ('cfgDefaultServer', 'localhost');


# Path to smbclient program
# i.e. define ('cfgSmbClient', '/usr/bin/smbclient');

@define ('cfgSmbClient', 'smbclient');


# Socket options to SAMBA tools (do not touch if you are not sure)

@define ('cfgSocketOptions', 
'TCP_NODELAY IPTOS_LOWDELAY SO_KEEPALIVE SO_RCVBUF=8192 SO_SNDBUF=8192');


# ----------> you do not need to edit after this line !!! <-----------

@define ('cfgInlineStyle', 'on');

?>