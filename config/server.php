<?php

return [

    // The root helper install.sh puts in place (deploy/olt-panel-admin): the panel's
    // address and HTTPS from Settings → Server & HTTPS. The web server runs it
    // through sudo, allowed for this one file by /etc/sudoers.d/olt-panel.
    'admin_helper' => env('OLT_ADMIN_HELPER', '/usr/local/sbin/olt-panel-admin'),
    'admin_helper_sudo' => (bool) env('OLT_ADMIN_HELPER_SUDO', true),

];
