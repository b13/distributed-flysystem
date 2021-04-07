## Distributed Flysystem for TYPO3 Core

Imagine you run a multi-head environment with TYPO3. Each application
server is a "node".

As usual, there are caveats, and hurdles to work around with. Typically
you have local cache files ("var/"), and (ideally) a FAL driver via a
remote shared storage for "fileadmin". The database is on a cluster,
and you run a Redis sentinel for shared caches.

But what about the other files that TYPO3 sometimes generates?

In an ideal world, TYPO3 would be handling most of the logic already, but due to
some extensions or "compressed" files, some files get generated on e.g. "node 20" in
typo3temp/ which aren't available on the other nodes.

In the past, we had NFS to work around this usually, but when NFS is not an option,
we built a "workaround" extension for this, based on Frank De Jonge's [Flysystem](https://flysystem.thephpleague.com/v2/docs/)
abstraction layer.

Every time a file in e.g. `typo3temp` gets accessed, and the file is not available
on this node, the other nodes are checked via SFTP and if one node has the
file, it is downloaded on the current nodes' typo3temp/ folder.

Currently, this extension is a "pull" solution - fetching the files
if needed - but a deeper integration with the Local driver of the File Abstraction
Layer should monitor this behavior as well in a separate step.

## Installation

Set it up via composer: `composer req b13/distributed-flysystem`.

## Configuration

Add a proper configuration for your SFTP connection to all your nodes in your
LocalConfiguration for example:

    $GLOBALS['TYPO3_CONF_VARS']['FILE']['flysystem'] = [
        'default' => [
            'rootPath' => '/path/from/sftp/ch-root/to/public/path/',
            'username' => 'b13',
            'password' => null,
            'port' => 2222,
            'privateKey' => Environment::getVarPath() . '/my-sftp_privkey',
            'passphrase' => 'take it or leave it',
            'timeout' => 10
        ],
        'nodes' => [
            'node01' => [
                'host' => '1.2.3.4'
                'alias' => 'primary'
            ],
            'node02' => [
                'host' => '1.2.3.5',
                'username' => 'b14',
            ],
        ],
    ];    

Modify your .htaccess file on each node to activate the "pulling" mechanism:

    # Rewrite non-existent fileadmin and typo3temp files to a custom eID script
	RewriteCond %{REQUEST_URI} "^(/fileadmin|/typo3temp/assets/).*" [NC]
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-l
	RewriteRule ^.*$ %{ENV:CWD}index.php?eID=flysystem [QSA,L]


## Caveats

It is expected that the `rootPath` variable is set to the folder of the public
directory of TYPO3's installation.

## License

As TYPO3 Core, _distributed flysystem_ is licensed under GPL2 or later. See the LICENSE file for more details.
