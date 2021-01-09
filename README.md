# KeePassWriter (kpx-writer-php)

This is is a PHP wrapper around the KeePassXC-cli binary (supplied with KeePassXC) providing
an API for creating KeePass databases.

Cleartext secrets are never written to the filesystem by the library.

Due to the complications arising from thie requirement, the library will only work on Linux/Unix/POSIX systems with the PCNTL ad POSIX extensions.

The wrapper is implemented as a single class - KeePassWriter. This exposes the following methods:

 * __construct($filename, $passphrase, $timeout, $exec) 
   * $filename is the path + name for the database to be created. Creation will fail if the file already exists
   * $passphrase is the Password for the KeePass database
   * $timeout (optional) is the maximum amount of time in seconds to allow for database creation
   * $exec (optional) is the executable to use for generating the database (defaults to `keepassxc-cli`)
 * additem($path, $title, $username, $secret, $url, $notes)
   * $path is the location in the database tree to store the item and is specified like a filesystem path with '/' as a seperator. The groups (equivalent to folders or directories on a filesystem) are created automaticaly - but see addgroup below.
   * $title is the name/label for the item
   * $username is the login account name for the target
   * $secret is the password for the target, or the encryption key or other secret (this is the data which will be enrypted)
   * $url is the location of the target
   * $notes is free text
 * addgroup($path, $notes, $icon)
   * Although additem will automatically create the required groups calling this method allows an icon and text description to be added
   * $path and $notes work the same way as in additem()
   * $icon (optional) expects an integer identitfying which icon to use for the group. These are defined as constants in include/kpx_icons.inc.php
 * changeParams($filename, $passphrase)
   * This updates the parameters set in the constructor.
   * This method allows multipe copies of a database to be created with different passphrases (without having to recreate the internal structure)
 * createdb()
   * writes the current dataset to a KeePass database

There is a more complete example in example.php, but for illustration purposes:
```
<?php

require("kpx-writer-php/include/KeePassWriter.inc.php");

$kpx=new KeePassWriter(getenv("HOME") . "/secrets.kdbx", "l3tm31n");
$kpx->additem("/linux/admin", "root@example.com", "root", "s3cr3t"
      , "ssh://root@example.com", "Does this host allow root ssh logins?");
$kpx->createdb();
```


		
