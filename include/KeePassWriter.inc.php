<?php
/**
 * @Project kxc-php
 * @author colin.mckinnon
 *
 * KeePassWriter provides an API wrapper around the
 * Keepassxc-cli binary, allowing the creation
 * of a KeePass database.
 *
 * No data is committed to the local storage unencrypted (unless in swap)
 */
require_once('kpx_icons.inc.php'); // define contstants to describe icons

class KeePassWriter {
	private $filename;
	private $passphrase;
	private $data;
	private $exec; // path to keepassxc-cli binary
	private $timeout;
	public  $keepass_error; // will contain any errors reported by the keepassxc-cli runtime
	/**
	 * param string $filename path to write database to (dirs will created if permitted) MUST NOT EXIST!
	 * param string $passphrase passphrase used to encrypt the data
	 *
	 * Permform some sanity checks and prepare the stub data
	 */
	function __construct($filename, $passphrase, $timeout=30, $exec=false)
	{
		$this->changeParams($filename, $passphrase);
		if ($exec && !is_executable($exec)) {
			trigger_error("Supplied path for keepassxc-cli is not executable", E_USER_ERROR);
		}
		if (!$exec) {
			$exec="keepassxc-cli";
		}
		if (!function_exists('posix_mkfifo')) {
			trigger_error("KeePassWriter requires the POSIX extension", E_USER_ERROR);
		}
		if ((integer)$timeout) {
			$this->timeout=$timeout;
		} else {
			$this->timeout=20;
		}
		$this->exec=$exec;
		$this->keepass_error="Not yet invoked";
		$this->Data=array('Name'=>'Root', 'IconID'=>KPX_ICON_DEFAULT, 'Notes'=>'', 'g'=>array(), 'e'=>array());
	}
	/**
	 * @param string $filename - set the path for the new database
	 * @param string $passphrase - set the passphrase for the new database
	 *
	 * It's a rather involved process populating the dataset. This method is here to simplify
	 * the creation of multiple instances with different passphrases without
	 * having to regenerate the data structure
	 */
	public function changeParams($filename, $passphrase)
	{
		if (file_exists($filename)) {
                        trigger_error("File already exists", E_USER_ERROR);
                }
		$path=realpath(dirname($filename));
                if (!is_dir($path) && !mkdir($path, true)) {
                        trigger_error("Path does not exist / cannot be created", E_USER_ERROR);
                }
		$this->filename=$filename;
		$this->passphrase=$passphrase;
	}
	/**
	 * @param resource $handle Open file handle to write data to
	 *
	 * Normally this should not be called directly but is exposed as a public
	 * function for debugging purposes ( $kpx->writedata(STDOUT); )
	 */
	public function writedata($handle)
	{
		fputs($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n");
		fputs($handle, "<KeePassFile>\n<Root>\n");
		$this->writegroups($handle, $this->data['Root']);
		fputs($handle, "</Root>\n</KeePassFile>\n");
	}
	private function writegroups($handle, $arr)
	{
		fputs($handle, "<Group>\n<Name>" 
                    . htmlspecialchars($arr['Name'], ENT_XML1, 'UTF-8')
		    . "</Name>");
		if (isset($arr['Notes'])) {
			fputs($handle, "<Notes>"
			 . htmlspecialchars($arr['Notes'], ENT_XML1, 'UTF-8')
			 . "</Notes>\n");
		}
		if (!isset($arr['IconID']) || !(integer)$arr['IconID']) {
			$arr['IconID']=KPX_ICON_DEFAULT; // Default folder
		}
		fputs($handle, "<IconID>" . (integer)$arr['IconID'] . "</IconID>\n");
		foreach($arr['g'] as $subgroup) {
			$this->writegroups($handle, $subgroup);
		}
		foreach($arr['e'] as $entry) {
			fputs($handle, "<Entry>\n$entry\n</Entry>\n");
		}
		fputs($handle, "</Group>\n");
		
	}
	/**
	 * param string $path Hierarchy of groups written as a file path e.g. infrastructure/switches/Cisco
	 * param string $notes Any runtime supplied description of the group
	 * param integer $icon id for one of the Keepassxc icons
	 *
	 * Keepass "Groups" are folders - analogous to directories on a filesystem
	 * At the top level, Keepass has a group named "Root" but this is automatically added by this lib
	 */
	public function addgroup($path, $notes, $icon=false)
	{
		$parts=explode("/", "Root/" . trim($path, " /\r\n"));
		$this->buildpath($parts, $this->data, $notes, $icon);
	}
	/**
	 * Recursive slave function to addgroup() method
	 */
	private function buildpath($parts, &$arr, $notes, $icon) 
	{
		$part=array_shift($parts);
		if (!isset($arr[$part])) {
			$arr[$part]=array('Name'=>$part, 'e'=>array(), 'g'=>array());
		}
		if (count($parts)) {
			$this->buildpath($parts, $arr[$part]['g'], $notes, $icon);
		} else {
			$arr[$part]['Notes']=$notes;
			$arr[$part]['IconID']=$icon;
		}
	}
	/**
	 * param string $path See "addgroup" method
	 * param string $title The name for the secret record
	 * param string $username The account name on the target
	 * param string $secret The authentication token (usually a password) for the target
	 * param string $url The URL for the target
	 * param string $notes Any runtime supplied description of the record
	 *
	 * Creates the XML for a secret record and places it in the specified group hierarchy
	 */
	public function additem($path, $title, $username, $secret, $url, $notes)
	{
		$item="<String><Key>Title</Key><Value>" 
		. htmlspecialchars($title, ENT_XML1, 'UTF-8') 
		. "</Value></String>\n"
		. "<String><Key>UserName</Key><Value>"
		. htmlspecialchars($username, ENT_XML1, 'UTF-8')
		. "</Value></String>\n"
		. "<String><Key>Password</Key><Value ProtectInMemory=\"True\">"
		. htmlspecialchars($secret, ENT_XML1, 'UTF-8')
		. "</Value></String>\n"
		. "<String><Key>URL</Key><Value>"
                . htmlspecialchars($url, ENT_XML1, 'UTF-8')
                . "</Value></String>\n"
		. "<String><Key>Notes</Key><Value>"
                . htmlspecialchars($notes, ENT_XML1, 'UTF-8')
                . "</Value></String>";
		$parts=explode("/", "Root/" . trim($path, " /\r\n"));
		$this->builditem($parts, $this->data, $item);

	}
	/**
	 * Recursive slave function to additem method
	 */
	private function builditem($pathparts, &$arr, $item)
	{
		$pathpart=array_shift($pathparts);
		if (!isset($arr[$pathpart]) || !is_array($arr[$pathpart])) {
			$arr[$pathpart]=array('Name'=>$pathpart, 'g'=>array(), 'e'=>array());
		}
		if (count($pathparts)) {
			$this->builditem($pathparts, $arr[$pathpart]['g'], $item);
		} else {
			$arr[$pathpart]['e'][]=$item;
		}
	}
	/**
	 * Create a Keepass data using the previously supplied data
	 *
	 * this gets complicated due to the fact that opening a FIFO for writing
	 * blocks until a reader also opens the file.
	 * To deal with this, the code calls pcntl_fork()
	 * - the child opens the FIFO, sends the data in and exits
	 * - the original process starts keepassxc-cli and sends it the passphrase
	 *   then waits to see how keepassxc-cli responds
	 * Hopefully the OS memory COW means that we don't double the memory usage!
	 */
	function createdb()
	{
		$tmpfile=$this->mkfifo();
		// print "assigned filename $tmpfile\n";
		// print "Master process is " . getmypid() . "\n";
		$pid=pcntl_fork();
		if (-1 == $pid) {
			trigger_error("Failed to fork", E_USER_ERROR);
		}
		if (0==$pid) {
			// this is the spawned process
			// which will write to the fifo
			// print "Writer process is " . getmypid() . "\n";
			$this->datasender($tmpfile);
			exit(0);
		} else {
			// this is the controlling process
			$result=$this->controlslaves($tmpfile,$pid);
		}
		unlink($tmpfile);
		return $result;
	}
	/**
	 * @param string $tmpfile path+name of fifo 
	 * 
	 * Invoked in the child process ONLY
	 */
	private function datasender($tmpfile)
	{
		if (function_exists('pcntl_async_signals')) {
			pcntl_async_signals(true);
		}
		pcntl_signal(SIGALRM, array($this, 'timeout'));
		pcntl_alarm($this->timeout); // Note, only set in child
		$fifo=fopen($tmpfile, "w"); // this blocks until fifo also open for reading
					// hence earlier pcntl_fork()
                // print "opened fifo for write\n";
                if (!is_resource($fifo)) {
                        trigger_error("Failed to open fifo for write", E_USER_ERROR);
                }
                // print "fifo opened\n";
		$this->writedata($fifo);
                // print "data sent\n";
		fclose($fifo);
		// print "datasender has closed fifo\n";
	}
	/**
	 * in case child gets blocked indefinitely....
	 */
	function timeout()
	{
		exit(1);
	}
	/**
	 * @param string $tmpfile path+name of fifo
	 * @param string $pid process id of forked (child) instance
	 * @return bool true if database created
	 *
	 * invoked in  the parent process ONLY
	 */
	private function controlslaves($tmpfile, $pid)
	{
		$io=array();
                $iodef=array(
                        0 => array('pipe', 'r'),
                        1 => array('pipe', 'w'),
                        2 => array('pipe', 'w'));
                $cmd=$this->exec . " import " . escapeshellarg($tmpfile) . " " . escapeshellarg($this->filename);
                $proc=proc_open($cmd, $iodef, $io, sys_get_temp_dir());
                if (!is_resource($proc)) {
                        trigger_error("Failed to invoke executable");
                }
		$this->keepass_error="KeePass import process invoked";
                $this->setpassphrase($io);
                // print "Key set\n";
		// $cmd will now open the fifo and start reading from it.
		// we wait for the sender to finish....
		$status=0;
		pcntl_waitpid($pid, $status);
		if (!pcntl_wifexited($status)) { // did it fail to exit cleanly?
			trigger_error("Forked instance did not exit cleanly", E_USER_WARNING);
			$result=false;
		} else {
			$result=true;
		}
		$response=stream_get_contents($io[1]);
                $this->keepass_error=trim(stream_get_contents($io[2]));
		if (!strstr($response, 'Successfully imported database')) {
			$result=false;
		}
                fclose($io[1]);
                fclose($io[2]);
                fclose($io[0]);
                // print "response=$response\n====\nerr=$err_response\n";
		return $result;
	}
	/**
	 * Generate a unique filename for the FIFO
	 * (to mitigate but not eliminate MITM)
	 */
	private function mkfifo()
	{
		$tmpfile=tempnam(sys_get_temp_dir(), "KPX");
                if (!$tmpfile) {
                        trigger_error("Failed to create pipe file", E_USER_ERROR);
                }
                unlink($tmpfile);
                if (!posix_mkfifo($tmpfile, 0600)) {
                        trigger_error("Failed to create fifo", E_USER_ERROR);
                }
		return $tmpfile;	
	}
	/**
	 * @param array $io the 3 file handles created by proc_open
	 */
	private function setpassphrase($io)
	{
		$prompt='';

		while (!feof($io[2])) {
			$c=fgetc($io[2]);
			fputs(STDOUT, $c);
			$prompt.=$c;
			if (':'==$c) {
				break;
			}
		}
		if (feof($io[2]) || $prompt!='Enter password to encrypt database (optional):') {
			trigger_error("Unexpected prompt from executable " . base64_encode($prompt), E_USER_ERROR);
		}
		fputs($io[0], $this->passphrase . "\n");
		$prompt='';
		while (!feof($io[2])) {
			$c=fgetc($io[2]);
			$prompt.=$c;
			if (':'==$c) {
				break;
			}
		}
		$prompt=trim($prompt);
		if (feof($io[2]) || $prompt!='Repeat password:') {
			trigger_error("Unexpected second prompt from executable " . base64_encode($prompt), E_USER_ERROR);
		}
		fputs($io[0], $this->passphrase . "\n");
	}
}
