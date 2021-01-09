<?php

require_once('include/KeePassWriter.inc.php');

$kpx=new KeePassWriter(
    getenv("HOME") . "/sample.kdbx", // database to create
    "letmein",                       // passphrase for new database 
    20,                              // timeout for writing to keepassxc-cli (optional)
    '/usr/bin/keepassxc-cli');       // path to binary (optional) 

// groups are created automatically, but if we want to assign notes
// or specific icons then we use 'addgroup'
// addgroup($path, $notes, $icon=false)
$kpx->addgroup("/home", "Home folder", KPX_ICON_HOME);
$kpx->addgroup("/infrastructure/network", "Switches etc", KPX_ICON_NETWORK_BOXES);
$kpx->addgroup("/infrastructure/linux", "Linux hosts", KPX_ICON_TUX);
$kpx->addgroup("/infrastructure/external", "3rd Party services", KPX_ICON_CLOUD);
$kpx->addgroup("/infrastructure/Microsoft", "MS Windows hosts", KPX_ICON_MSWINDOWS);
$kpx->addgroup("/applications", "In-house apps", KPX_ICON_YELLOW_DOC);
$kpx->addgroup("/database", "Admin accounts", KPX_ICON_DB_BURGER_KEY);
$kpx->addgroup("/encryption", "Encryption keys", KPX_ICON_KEYRING);

// add some entries
// additem($path, $title, $username, $secret, $url, $notes)
$kpx->additem("/infrastructure/linux", "root@example.com"
    , "root", "sw0rd1sh", "ssh://root@example.com"
    , "Assunming the host is configured to allow root logins via ssh");
$kpx->additem("/infrastructure/network", "admin@10.1.1.254(Cisco)"
    , "admin", "pass123", "ssh://admin@10.1.1.254"
    , "Admin user on Cisco");

// we can display the generated XML....
// $kpx->writedata(STDOUT);

$kpx->createdb();

