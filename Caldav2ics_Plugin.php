<?php
/*
Dies ist die Entwicklungsversion hin zu einer 'Mehrere Kalender' Version, siehe ToDo.txt
Achtung: diese darf vorest nicht in ein öffentliches Repo kommen !
10.02.19: funktioniert auf hoernerfranzracing.de :)
*/

include_once('Caldav2ics_LifeCycle.php');

class Caldav2ics_Plugin extends Caldav2ics_LifeCycle {
	
	// not yet really DONE: check all Calendar Entrys for valid data
	public function CheckMandatoryOptions() {
		$CalendarOptions = $this->getCalendarOptions();
		$calNum = 1;
		foreach(($calURLs = unserialize($CalendarOptions['caldav2ics_calendar_urls']))  as $calURL) {
			if (false == wp_http_validate_url($calURL)) {
				return "Invalid Calendar URL:".$calURL." for Calendar # ".$calNum;
			}
			++$calNum;
		}
		/*
		Versuch, das hier: https://wordpress.org/support/topic/issue-cant-activate/ zu fixen.
		siehe https://mycyberuniverse.com/how-fix-cant-use-function-return-value-write-context.html 13.01.19
		*/
		// 10.02.19: das funktioniert alles nicht, weil im Fall einer leeren Eingabe einfach $CalendarOptions... leer ist
		// und damit in der foreach Schleife garnicht auftaucht
		// TODO (?): anderer Test
		$calNum = 1;
		foreach(($calUsers = unserialize($CalendarOptions['caldav2ics_calendar_users']))  as $calUser) {
			$Username = $calUser;	//	($calUser);
			if (empty($calUser)) {
				return "Invalid (empty) Username for Calendar # ".$calNum;
			}
			++$calNum;
		}
		$calNum = 1;
		foreach(($calPwds = unserialize($CalendarOptions['caldav2ics_calendar_passwords']))  as $calPwd) {
			$Password = trim($calPwd);
			if (strlen($Password) < 2) {
				return "Invalid (empty) Password for Calendar # ".$calNum;
			}
			++$calNum;
		}
		return '';
	}
	
	/**
	 * See: http://plugin.michael-simpson.com/?page_id=31
	 * @return array of option meta data.
	 */
	public function getOptionMetaData() {
		return array(
			//'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
			'CronInterval' => array(__('Cron Interval', 'wp-caldav2ics'),
										'daily', 'twicedaily', 'hourly'),
			'Logging' => array(__('enable Logging', 'wp-caldav2ics'),'true','false'),
		);
	}
	
	protected function getOptionValueI18nString($optionValue) {
		$i18nValue = parent::getOptionValueI18nString($optionValue);
		return $i18nValue;
	}

	protected function initOptions() {
		$options = $this->getOptionMetaData();
		if (!empty($options)) {
			foreach ($options as $key => $arr) {
				if (is_array($arr) && count($arr > 1)) {
					$this->addOption($key, $arr[1]);
				}
			}
		}
	}

	public function getPluginDisplayName() {
		return 'WP-CalDav2ICS';
	}

	protected function getMainPluginFileName() {
		return 'wp-caldav2ics.php';
	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=101
	 * Called by install() to create any database tables if needed.
	 * Best Practice:
	 * (1) Prefix all table names with $wpdb->prefix
	 * (2) make table names lower case only
	 * @return void
	 */
	protected function installDatabaseTables() {
		//		global $wpdb;
		//		$tableName = $this->prefixTableName('mytable');
		//		$wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
		//			`id` INTEGER NOT NULL");
	}

	/**
	 * See: http://plugin.michael-simpson.com/?page_id=101
	 * Drop plugin-created tables on uninstall.
	 * @return void
	 */
	protected function unInstallDatabaseTables() {
		//		global $wpdb;
		//		$tableName = $this->prefixTableName('mytable');
		//		$wpdb->query("DROP TABLE IF EXISTS `$tableName`");
	}

	/**
	 * Perform actions when upgrading from version X to version Y
	 * See: http://plugin.michael-simpson.com/?page_id=35
	 * @return void
	 */
	public function upgrade() {
		// DONE: do the actual Upgrade from the single Calendar Version
		$DeprecatedCalUrl = $this->getOption('CalendarURL');
		if (strlen(trim($DeprecatedCalUrl)) > 0)	{
			$cal_url_Array = array($DeprecatedCalUrl);
			update_option('caldav2ics_calendar_urls', serialize($cal_url_Array));
			$this->deleteOption('CalendarURL');
		}
		$DeprecatedCalUser = $this->getOption('Username');
		if (strlen(trim($DeprecatedCalUrl)) > 0)	{
			$cal_url_Array = array($DeprecatedCalUser);
			update_option('caldav2ics_calendar_users', serialize($cal_url_Array));
			$this->deleteOption('Username');
		}
		$DeprecatedCalPw = $this->getOption('Password');
		if (strlen(trim($DeprecatedCalPw)) > 0)	{
			$cal_url_Array = array($DeprecatedCalPw);
			update_option('caldav2ics_calendar_passwords', serialize($cal_url_Array));
			$this->deleteOption('Password');
		}
		$DeprecatedCalFile = $this->getOption('CalendarFile');
		if (strlen(trim($DeprecatedCalFile)) > 0)	{
			$cal_url_Array = array($DeprecatedCalFile);
			update_option('caldav2ics_calendar_files', serialize($cal_url_Array));
			$this->deleteOption('CalendarFile');
		}
		$NewOptions = $this->getCalendarOptions();
		//	print_r($NewOptions);
	}

	// activate wp-cron scheduled ics File Generation, see https://developer.wordpress.org/plugins/cron/scheduling-wp-cron-events/
	public function activate() {
		set_transient( "caldav2ics", "activated", 3 );	// muss hierhin, NICHT in addActionsAndFilters() !
	}

	/**
	* Admin Notice on Activation.
	*/
	public function activation_notice(){
		/* Check transient */
		if ( "activated" == get_transient( "caldav2ics" )) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>
				<?php _e('Caldav2ICS Plugin activated - '); ?>
				<strong>
				<?php _e('Be sure to set correct Values in Plugin Admin Page !'); 
				?>
				</strong>.</p>
			</div>
			<?php
			delete_transient( 'caldav2ics' );
		}
	}

	// deactivate wp-cron scheduled ics File Generation
	public function deactivate() {
		register_deactivation_hook( __FILE__, 'bl_deactivate' );
		add_action( 'bl_deactivation_hook', 'bl_deactivate' );
	}

	public function bl_deactivate() {
		$timestamp = wp_next_scheduled( 'bl_cron_hook' );
		wp_unschedule_event( $timestamp, 'bl_cron_hook' );
		remove_action('bl_cron_hook', 'bl_cron_exec');
		// remove Admin Panel
		remove_action('admin_menu', 'addSettingsSubMenuPage');
	}

	// this is the actual code to be executed by WP Cron:
	public function bl_cron_exec() {
		// Read CalDav Calendar and write to ics File
		if (($this->getOption("Logging","true") == 'true') || WP_DEBUG)	{
			$LogEnabeled = true;
		}	else{
			$LogEnabeled = false;
		}
		// $icsdir = plugin_dir_path( __FILE__ );	// do not store data in plugin dir
		$icsdir = ABSPATH."/wp-content/uploads/calendars";	// 09.11.18
		if (! file_exists($icsdir))	{
			wp_mkdir_p($icsdir);
		}
		$LogFile = plugin_dir_path( __FILE__ ).'/cron.log';
		if ($LogEnabeled)	{
			$loghandle = fopen($LogFile, 'w') or wp_die('Cannot open file:  '.$LogFile);
			$date = new DateTime();
			$date = $date->format("y:m:d h:i:s");
			fwrite($loghandle, "Log created on ".$date."\n");
		}
		
		// Setup the calendar URL and the credentials here, from the Plugin Settings Page
		/*
		$this->Mandatory_Options['CalendarURL'] = $this->getOption('CalendarURL');
			$this->Mandatory_Options['Username'] = $this->getOption('Username');
			$this->Mandatory_Options['Password'] = $this->getOption('Password');
		*/
		if (strlen($this->CheckMandatoryOptions()) > 1)	{
			echo "<p style='color:red;font-weight:bold;'>";
			_e('Error - You have currently one or more invalid mandatory Options set:');
			echo "<br>".$this->CheckMandatoryOptions()."</p>";
			echo("Invalid CalendarURL(s) and/or Credentials, aborting!<br>");
			if ($LogEnabeled)	{
				fwrite($loghandle, "Invalid CalendarURL(s) and/or Credentials, aborting!\n");
				fclose($loghandle);
			}
			return;	// do not proceed if invalid CalendarURLs found
		}
		// DONE: Loop all Calendars 
		$CalendarOptions = $this->getCalendarOptions();
		$CalendarURLs = unserialize($CalendarOptions['caldav2ics_calendar_urls']);
		$CalendarUsers = unserialize($CalendarOptions['caldav2ics_calendar_users']);
		$CalendarPWs = unserialize($CalendarOptions['caldav2ics_calendar_passwords']);
		$CalendarFiles = unserialize($CalendarOptions['caldav2ics_calendar_files']);
		$index = 0;	// NICHT 1 !
		foreach ($CalendarURLs as $CalendarURL) {
			$userkeys = array_keys($CalendarUsers);
			$CalendarUser = $CalendarUsers[$userkeys[$index]];
			$pwkeys = array_keys($CalendarPWs);
			$CalendarPW = $CalendarPWs[$pwkeys[$index]];
			if (empty(trim($CalendarUser)) || empty(trim($CalendarPW)))	{
				echo "<p style='color:red;font-weight:bold;'>";
				_e('Error - You have currently one or more invalid mandatory Options set:');
				echo("Invalid Calendar Credentials, skip this Calendar:".$CalendarURL."  !</p>");
				break;
			}
			$filekeys = array_keys($CalendarFiles);
			$CalendarFile = $CalendarFiles[$filekeys[$index]];
			if (empty(trim($CalendarFile)))
				$CalendarFile = "calendar".$index.".ics";
			$ICalFile = $icsdir.'/'.$CalendarFile;
			/*
			echo "Calnr:".$index." URL:  ".$CalendarURL."<br>";
			echo "Calnr:".$index." User: ".$CalendarUser."<br>";
			echo "Calnr:".$index." PW: ".$CalendarPW."<br>";
			echo "Calnr:".$index." File: ".$ICalFile."<br>";
			*/
			//	break;
			if ($LogEnabeled)	{
				fwrite($loghandle, "CalendarURL:".$CalendarURL."\n");
				/* disable writing user/pw to LogFile 22.01.19
				fwrite($loghandle, "Username".$this->Mandatory_Options['Username']."\n");
				fwrite($loghandle, "Pw:".$this->Mandatory_Options['Password']."\n");
				*/
			}	
			
			// Simple caching system, feel free to change the delay
			$fmdelay = 5;	// caching not really needed, as ICalFile will be created only at cron Intervals
			if (file_exists($ICalFile)) {
				$last_update = filemtime($ICalFile);
			} else {
				$last_update = 0;
			}
			if ($last_update + $fmdelay < time()) {

				// Prepare request body, MANDATORY !
				$doc  = new DOMDocument('1.0', 'utf-8');
				$doc->formatOutput = true;

				$query = $doc->createElement('c:calendar-query');
				$query->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:c', 'urn:ietf:params:xml:ns:caldav');
				$query->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:d', 'DAV:');

				$prop = $doc->createElement('d:prop');
				$prop->appendChild($doc->createElement('d:getetag'));
				$prop->appendChild($doc->createElement('c:calendar-data'));
				$query->appendChild($prop);

				$prop = $doc->createElement('c:filter');
				$filter = $doc->createElement('c:comp-filter');
				$filter->setAttribute('name', 'VCALENDAR');
				$prop->appendChild($filter);
				$query->appendChild($prop);

				$doc->appendChild($query);
				$body = $doc->saveXML();
				
				// new approach using wp_remote_request() 16.11.18
				$args = array(
				'headers' => array(
				'Authorization' => 'Basic '. base64_encode( $CalendarUser . ':' . $CalendarPW ),
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
				'Prefer' => 'return-minimal'),
				'method' => 'REPORT',
				'body' => $body,
				);

				$response = wp_remote_request( $CalendarURL, $args ); // this is now an Object of Type Requests_Utility_CaseInsensitiveDictionary !
				
				// retrieve body from response:
				$body = wp_remote_retrieve_body($response);
				$body_r = print_r($body, true);  // keep string array representation of body for logging and analysis 13.01.19
				if ($LogEnabeled) { 
					fwrite($loghandle, ($body_r));
				}
				
				// Get the useful part of the response
				
				// first check which Tag the Server returns to mark calendar data	13.01.19
				$Tag = '//cal:calendar-data';	// Default
				if (stripos($body, 'c:calendar-data'))  {	//  synology nas
						$Tag = '//C:calendar-data';
				}
				if (stripos($body, 'cal:calendar-data'))  {  // sabre.io 
						$Tag = '//cal:calendar-data';
				}
				if (stripos($body, '<calendar-data'))  {  // mailbox.org OX
						$Tag = '//calendar-data';
				}
				if ($LogEnabeled) { 
					fwrite($loghandle, "Tag:".$Tag."\n");
				}
				$xmlStr = $body_r;
				if (stripos($body, '<calendar-data'))  {  // mailbox.org , md2002 22.01.19
					if (stripos($CalendarURL, 'dav.mailbox.org'))	{
						$xmlStr = str_replace('<![CDATA[', '', $xmlStr);	// remove CDATA cruft that prevents $xml->xpath from working
						$xmlStr = str_replace(']]>', '', $xmlStr);
						$xmlStr = str_replace('xmlns=', 'ns=', $xmlStr); // see comments on http://php.net/manual/de/simplexmlelement.xpath.php
					}
				}
				if ($LogEnabeled)   fwrite($loghandle, $xmlStr);
				$xml = new SimpleXMLElement($xmlStr);   // (re-)create proper xml Object
				$data = $xml->xpath($Tag);   // use Tag found in response to exctract data, see above 13.01.19
				if (false == $data)	{	// issue Error Message if $xml->xpath cannot be evaluated
					$handle = fopen($ICalFile, 'w') or wp_die('Cannot open file:  '.$ICalFile);
					fwrite($handle, "ERROR: Your Server's response is invalid and cannot be parsed - please enable Logging and check the Logfile !\n");
					fclose($handle);
					echo "<p style='color:red;font-weight:bold;'>";
					_e("ERROR: Your Server's response is invalid and cannot be parsed - please enable Logging and check the Logfile !");
					echo "</p>";
					return;
				}
				$data_r = print_r($data, true);
				if ($LogEnabeled) { 
					fwrite($loghandle, "data:\n");
					fwrite($loghandle, ($data_r));
				}
				
				// create valid ICS File with only ONE Vcalendar !
				$handle = fopen($ICalFile, 'w') or wp_die('Cannot open file:  '.$ICalFile);
				// write VCALENDAR header
				fwrite($handle, 'BEGIN:VCALENDAR'."\r\n");
				fwrite($handle, 'VERSION:2.0'."\r\n");
				fwrite($handle, 'PRODID:-//hoernerfranzracing/wp-caldav2ics plugin'."\r\n");
				// find and write TIMEZONE data, new feature, 27.12.19
				$skip = true;
				$lines = explode("\n", $data_r);
				foreach ($lines as $line)   {
					if ($this->startswith($line,'BEGIN:VTIMEZONE'))	{
						$skip = false;
					}
					if ( !$skip )	{
						fwrite($handle, $line."\r\n"); // write everything between 'BEGIN:VTIMEZONE' and 'END:VTIMEZONE'
						// echo $line."\n";
					}
					if ($this->startswith($line,'END:VTIMEZONE'))	{
						$skip = true;
					}
				}
				// exctract events
				// parse $data as $vcalendars, do NOT write VCALENDAR header for each one, just the event data /TZ data etc...
				foreach ($data as $vcalendars) {
					$lines = explode("\n", $vcalendars);
					$skip = false;
					foreach ($lines as $line) {
						$line = trim($line);
						if (strlen($line))	{
							if ($this->startswith($line,'BEGIN:VCALENDAR'))	{
								$skip = true;
							}
							if ($this->startswith($line,'PRODID:'))	{
								$skip = true;
							}
							if (strstr($line,'VERSION:'))	{
								$skip = true;	// VERSION can appear in different places
							}
							if ($this->startswith($line,'CALSCALE:'))	{
								$skip = true;
							}
							if ($this->startswith($line,'BEGIN:VEVENT'))	{
								$skip = false;
								fwrite($handle, "\r\n");	// improves readability, but triggers warning in validator :)
							}
							if ($this->startswith($line,'END:VCALENDAR'))	{
								$skip = true;
							}
							if ( !$skip )	{
								fwrite($handle, $line."\r\n");
							}
						}
					}
				}
				fwrite($handle, 'END:VCALENDAR'."\r\n");
			}
			++$index;
		}
		fclose($handle);
		if ($LogEnabeled) { 
			fclose($loghandle);
		}
	}

	public function addActionsAndFilters() {
		// Add options administration page (to SettingsSubMenuPage)
		// http://plugin.michael-simpson.com/?page_id=47
		add_action('admin_menu', array(&$this, 'addSettingsSubMenuPage'));

		// Add Actions & Filters
		// http://plugin.michael-simpson.com/?page_id=37
		$cron_interval = $this->getOption("CronInterval","daily");
		add_action( 'bl_cron_hook', array(&$this, 'bl_cron_exec'));
		if ( ! wp_next_scheduled( 'bl_cron_hook' ) ) {
			wp_schedule_event( time(), $cron_interval, 'bl_cron_hook' );
		}	
		else {	// re-schedule bl_cron_hook, to reflect evtl. changes of time interval 
			$timestamp = wp_next_scheduled( 'bl_cron_hook' );
			wp_unschedule_event( $timestamp, 'bl_cron_hook');	// unschedule currently scheduled event
			wp_schedule_event( $timestamp, $cron_interval, 'bl_cron_hook' );	// do NOT use time() here !!!	// schedule new event with (possibly) different cron_interval
		}
		// enqueue scripts and styles for admin pages	10.02.19
		add_action('admin_enqueue_scripts', array(&$this, 'enqueueAdminPageStylesAndScripts'));

		// display activation message
		/* Add admin notice */
		add_action( 'admin_notices', array(&$this, 'activation_notice' ));	// wichtig: array(&this ...) !
	}
	
	public function enqueueAdminPageStylesAndScripts() {
		wp_enqueue_style('style', plugins_url('/css/style.css', __FILE__));
	}
	
	private function startswith ($string, $stringToSearchFor) {
		if (substr(trim($string),0,strlen($stringToSearchFor)) == $stringToSearchFor) {
				// the (trimmed) string starts with the string you're looking for
				return true;
		} else {
				// the string does NOT start with the string you're looking for
				return false;
		}
	}

	// new approach, see ics-import from wp-ics-importer 02.02.19
	function getCalendarOptions() {
		$CalendarOptions = array(
			'caldav2ics_calendar_urls' => array(),
			'caldav2ics_calendar_users' => array(),
			'caldav2ics_calendar_passwords' => array(),
			'caldav2ics_calendar_files' => array(),
		);
		// wichtig !!!:
		$CalendarOptions['caldav2ics_calendar_urls' ] = get_option('caldav2ics_calendar_urls' );
		$CalendarOptions['caldav2ics_calendar_users' ]= get_option('caldav2ics_calendar_users' );
		$CalendarOptions['caldav2ics_calendar_passwords' ]= get_option('caldav2ics_calendar_passwords' );
		$CalendarOptions['caldav2ics_calendar_files' ]= get_option('caldav2ics_calendar_files' );
		return $CalendarOptions;
	}
	
	/**
	* Creates HTML for the Administration page to set options for this plugin.
	* this is an Override of the method from Caldav2ics_OptionsManager.php
	* (Override allows for Input Validation)
	* @return void
	*/
	public function settingsPage() {
		if (!current_user_can('manage_options')) {
				wp_die(__('You do not have sufficient permissions to access this page.', 'wp-caldav2ics'));
		}
		
		// DONE: get/set $CalendarOptions (=array of $CalendarOptions):
		$CalendarOptions = $this->getCalendarOptions();
		//	print_r($CalendarOptions);
		$GeneralOptionMetaData = $this->getOptionMetaData();
		if (isset($_POST['updateSettings'])) {	// 'submit' Button pressed ? -> show Message
			// Save Posted Options for Calendars
			$cal_url_Array=array();
			foreach($_POST['caldav2ics_calendar_urls'] as $k=>$val) {
				if(!empty($val)) {
					$cal_url_Array[$k+1] = $val;
				}
			}
			$CalendarOptions['caldav2ics_calendar_urls'] = serialize($cal_url_Array);
			update_option('caldav2ics_calendar_urls', serialize($cal_url_Array));
			$cal_user_Array=array();
			foreach($_POST['caldav2ics_calendar_users'] as $k=>$val) {
				if(!empty($val)) {
					$cal_user_Array[$k+1] = $val;
				}
			}
			$CalendarOptions['caldav2ics_calendar_users'] = serialize($cal_user_Array);
			update_option('caldav2ics_calendar_users', serialize($cal_user_Array));
			$cal_password_Array=array();
			foreach($_POST['caldav2ics_calendar_passwords'] as $k=>$val) {
				if(!empty($val)) {
					$cal_password_Array[$k+1] = $val;
				}
			}
			$CalendarOptions['caldav2ics_calendar_passwords'] = serialize($cal_password_Array);
			update_option('caldav2ics_calendar_passwords', serialize($cal_password_Array));
			
			if (!empty($this->CheckMandatoryOptions()))	{
				echo "<p style='color:red;font-weight:bold;'>";
				_e('Error - You have currently one or more invalid mandatory Options set:');
				echo "<br>".$this->CheckMandatoryOptions()."</p>";
				echo "aborting!";
				return;
			}
			
			$cal_files_Array=array();
			foreach($_POST['caldav2ics_calendar_files'] as $k=>$val) {
				if(!empty($val)) {
					$cal_files_Array[$k+1] = $val;
				}
			}
			$CalendarOptions['caldav2ics_calendar_files'] = serialize($cal_files_Array);
			update_option('caldav2ics_calendar_files', serialize($cal_files_Array));
			
			if ($GeneralOptionMetaData != null) {
				foreach ($GeneralOptionMetaData as $aOptionKey => $aOptionMeta) {
					if (isset($_POST[$aOptionKey])) {
						$this->updateOption($aOptionKey, $_POST[$aOptionKey]);
					}
				}
			}
			// DONE: get/set ICS files Array correctly
			$ICSfileURLs = array();
			foreach ($cal_files_Array as $cal_file) {
				$cal_file_url = get_site_url()."/wp-content/uploads/calendars/".$cal_file;
				$ICSfileURLs[] = $cal_file_url;
			}
			$LogFileURL = get_site_url().'/wp-content/plugins/wp-caldav2ics/cron.log';
			?>
			<div class="fade updated" id="message"><p><strong><?php _e("Settings Updated - please check Your generated ICS File(s) at: ", "wp-calda2ics");
			echo "<br>";
			?></strong></p>
			<p>
			<?php
			foreach($ICSfileURLs as $ics_url) {
				echo "<a href='$ics_url'>$ics_url</a><br>";
			}
			?>
			</p>
			<p><?php _e("(In case anything does not work as expected, please enable Logging and check the ", "wp-calda2ics");?><a href='<?php echo $LogFileURL;?>' target='_blank'>Logfile</a> ).</p></div>
			<?php
			// DONE: re-enable cron_exec (disabeled for development 03.02.19)
			$this->bl_cron_exec();	// create ICalFile when 'submit' pressed !
		}
		
		// HTML for the page
		$settingsGroup = get_class($this) . '-settings-group';
		?>
		<div class="wrap">
		<!-- removed Table System Settings as this has nothing to do with wp-caldav2ics 19.10.18 -->

		<h2><?php echo $this->getPluginDisplayName(); echo ' '; _e('Settings', 'wp-caldav2ics'); ?></h2>

		<form method="post" action="">
		<?php settings_fields($settingsGroup); ?>
		<!-- DONE: put styles in plugin css file	(see addActionsAndFilters() )-->
		<table class="plugin-options-table"><tbody>
		<tr valign="top"><th scope="row">CalDav Calendar URL(s) *</th>
		<td>
			<?php
			$cal_url_Array = unserialize($CalendarOptions['caldav2ics_calendar_urls']);
			if(is_array($cal_url_Array)) {
			foreach($cal_url_Array as $key=>$val) { 
				//	print("cal:".$val); ?>
				<input type="text" name="caldav2ics_calendar_urlsKey" value="<?php _e($key, 'wp-caldav2ics') ?>" style="width:40px;" disabled="disabled" />
				<input type="text" name="caldav2ics_calendar_urls" class="calendars_options" value="<?php _e($val, 'wp-caldav2ics') ?>" /><br />
			<?php } 
			} ?>
			<input type="text" id="addNewKey" name="caldav2ics_calendar_urlsKey" value="" style="width:40px;" value="<?php _e($key+1, 'wp-caldav2ics') ?>" disabled="disabled" />
			<input type="text" id="addNewURL" name="caldav2ics_calendar_urls" class="calendars_options" value="" />
			</td>
		</tr>
		<tr valign="top"><th scope="row">CalDav Calendar Username(s) *</th>
		<td>
			<?php
			$cal_user_Array = unserialize($CalendarOptions['caldav2ics_calendar_users']);
			if(is_array($cal_user_Array)) {
			foreach($cal_user_Array as $key=>$val) { //	print("user:".$val);?>
				<input type="text" name="caldav2ics_calendar_usersKey" value="<?php _e($key, 'wp-caldav2ics') ?>" style="width:40px;" disabled="disabled" />
				<input type="text" name="caldav2ics_calendar_users" class="calendars_options" value="<?php _e($val, 'wp-caldav2ics') ?>" /><br />
			<?php } 
			} ?>
			<input type="text" id="addNewKey" name="caldav2ics_calendar_usersKey" value="" style="width:40px;" value="<?php _e($key+1, 'wp-caldav2ics') ?>" disabled="disabled" />
			<input type="text" id="addNewUser" name="caldav2ics_calendar_users" class="calendars_options" value="" />
			</td>
		</tr>
		<tr valign="top"><th scope="row">CalDav Calendar Password(s) *</th>
		<td>
			<?php
			$cal_password_Array = unserialize($CalendarOptions['caldav2ics_calendar_passwords']);
			if(is_array($cal_password_Array)) {
			foreach($cal_password_Array as $key=>$val) { ?>
				<input type="text" name="caldav2ics_calendar_passwordKey" value="<?php _e($key, 'wp-caldav2ics') ?>" style="width:40px;" disabled="disabled" />
				<input type="text" name="caldav2ics_calendar_passwords" class="calendars_options" value="<?php _e($val, 'wp-caldav2ics') ?>" /><br />
			<?php } 
			} ?>
			<input type="text" id="addNewKey" name="caldav2ics_calendar_passwordKey" value="" style="width:40px;" value="<?php _e($key+1, 'wp-caldav2ics') ?>" disabled="disabled" />
			<input type="text" id="addNewPassword" name="caldav2ics_calendar_passwords" class="calendars_options" value="" />
			</td>
		</tr>
		<tr valign="top"><th scope="row">ICS Calendar File(s)</th>
		<td>
			<?php
			$cal_files_Array = unserialize($CalendarOptions['caldav2ics_calendar_files']);
			if(is_array($cal_files_Array)) {
			foreach($cal_files_Array as $key=>$val) { ?>
				<input type="text" name="caldav2ics_calendar_filesKey" value="<?php _e($key, 'wp-caldav2ics') ?>" style="width:40px;" disabled="disabled" />
				<input type="text" name="caldav2ics_calendar_files" class="calendars_options" value="<?php _e($val, 'wp-caldav2ics') ?>" /><br />
			<?php } 
			} ?>
			<input type="text" id="addNewKey" name="caldav2ics_calendar_filesKey" value="" style="width:40px;" value="<?php _e($key+1, 'wp-caldav2ics') ?>" disabled="disabled" />
			<input type="text" id="addNewFile" name="caldav2ics_calendar_files" class="calendars_options" value="" />
			</td>
		</tr>
		</tbody></table>
		<br />
		<table class="plugin-options-table"><tbody>
		<?php
				if ($GeneralOptionMetaData != null) {
			foreach ($GeneralOptionMetaData as $aOptionKey => $aOptionMeta) {
				$displayText = is_array($aOptionMeta) ? $aOptionMeta[0] : $aOptionMeta;
				?>
				<tr valign="top">
				<th scope="row"><p><label for="<?php echo $aOptionKey ?>"><?php echo $displayText ?></label></p></th>
				<td>
				<?php $this->createFormControl($aOptionKey, $aOptionMeta, $this->getOption($aOptionKey)); ?>
				</td>
				</tr>
				<?php
			}
		}
		?>
		</tbody></table>
		<?php	// alert User if invalid Options found (all Calendars)
		if (strlen($this->CheckMandatoryOptions()) > 0)	{
			echo "<p style='color:red;font-weight:bold;'>";
			_e('Error - You have currently one or more invalid mandatory Options set:');
			echo "<br>".$this->CheckMandatoryOptions()."</p>";
		}
		//	Explain Options, added 19.10.18:
		echo "<br>";
		_e('Note: Settings that end with * are mandatory, all others are optional, which will be replaced by Defaults, if not specified.', 'wp-caldav2ics'); echo("<br>");
		_e('Defaults are:', 'wp-caldav2ics');
		echo ("<ul><li>");
		_e('Cron Interval: daily', 'wp-caldav2ics');
		echo ("</li><li>");
		_e('enable Logging: false', 'wp-caldav2ics');
		echo ("</li><li>");
		_e('ICS File(s): calendar0.ics, calendar1.ics...', 'wp-caldav2ics');
		echo ("</li></ul>");
		_e('ICS File(s) must be specified without PATH, will be stored in uploads/calendars. Logfile (if Logging enabeled) will be cron.log in PluginDir.', 'wp-caldav2ics');
		?>
		<p class="submit">
		<input type="submit" class="button-primary" name="updateSettings"
		value="<?php _e('Save Changes', 'wp-caldav2ics') ?>"/>
		</p>
		</form>
		</div>
		<?php
    }
}
