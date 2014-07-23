#!/root/test-util/bin/envFS /root/test-util/bin/php
<?php
# Written by RANDHIER RAMLACHAN

if ( !defined('__DIR__') ) define('__DIR__', dirname(__FILE__));

$app_include_path = __DIR__ . DIRECTORY_SEPARATOR . "lib";

# All required files in correct order
$required_files = array('common.inc.php', 'class.BuildManager.inc.php', 'class.ServerBuild.inc.php', 'class.Config.inc.php');
# Chechs that all files exists
foreach ($required_files as $req => $value){
   $reqfile=($required_files[$req]);
   $regfile= $app_include_path . DIRECTORY_SEPARATOR . "$reqfile";
   if (!file_exists($regfile))
       die($_SERVER['PHP_SELF'] . ": error: cannot find $regfile\n");
   else
       require_once("$regfile");
}

#exit ("hahhahahahah\n");

date_default_timezone_set('America/New_York');

$program_name = "va_install.php";
$program_version = "0.1";
$program_written = "RANDHIER RAMLACHAN";

#Build Info
$build_version= "7.0";
$build_number= "7088";

# FTP info
$qastaging_server = "10.1.3.19";
$qastaging_user_name = "falconstor\\ftpcopy";
$qastaging_password = "copy101";
$qastaging_subfolder = "Stage/CDs/CDP-VA/FalconStor-CDPVA-7.0-7088-007";
$qastaging_vacdfolder = "Stage/CDs/";

# For now info will be static.  Will change later to either imput driven or config file
$vcenter = "192.168.15.91";
$vcenter_user = "LabAutomation";
$vcenter_password = "\$angbe123";
$esxserver = "192.168.15.67";
$datastore = "900_Shared_Storage_Generic_9";
$vaname = "CDPVA";
$tgtnetwork1 = "dvPG_Twelve3";
$tgtnetwork2 = "dvPG_Twelve6";

# Validates the VA type (-v) input
function verify_vainput ($vatype) {
    global $VA;
    if ("$vatype" == "") {
        DIE($_SERVER['PHP_SELF'] . " error: No Virtual Appliance type was specified. Please specify a type with the -v option\n");
    }  
    # Convert to upper case
    $vatypeup = strtoupper($vatype);

    $include_list = array("CDP", "NSS");
    if (!in_array($vatypeup, $include_list)){
        die($_SERVER['PHP_SELF'] . " error: $vatype is not a supported VA type.  Supported inputs are ".implode(", ",$include_list)."\n");
    }
    $VA = "${vatypeup}VA";    
    
}

# Get the current build on the FTP server
function build_list($vatype) {
    global $qastaging_server;
    global $qastaging_user_name;
    global $qastaging_password;
    global $qastaging_vacdfolder;
    global $build_version;
    global $build_number;
    global $vazipfile;
    # Function to verify the input is valid  
    verify_vainput($vatype);
    # Convert to upper case
    $vatypeup = strtoupper($vatype);
    # Checks that the imput is either CDP or NSS
    $include_list = array("CDP", "NSS");
    if (!in_array($vatypeup, $include_list)){
        die($_SERVER['PHP_SELF'] . " error: $vatype is not supported VA type.  Supported inputs are ".implode(", ",$include_list)."\n");
    }
    # Append to qastaging VA folder the VA type
    $VA = "${vatypeup}VA";    
    $qastaging_vacdfolder ="$qastaging_vacdfolder$vatypeup-VA";
    # Log into the FTP server.
    print_and_log("Connecting to $qastaging_server via FTP", "post_qastaging");
    $conn_id = ftp_connect($qastaging_server);
    $login_result = ftp_login($conn_id, $qastaging_user_name, $qastaging_password);
    # Check that connection was made
    if ((!$conn_id) || (!$login_result)) {
        echo_and_log("ERROR: FTP connection has failed! Attempted to connect to $ftp_server for user $ftp_user_name", "post_qastaging");
    } else {
        echo_and_log("Connected to $qastaging_server, for user $qastaging_user_name", "post_qastaging");
    }
    # Change to the stage directory
    ftp_chdir($conn_id, $qastaging_vacdfolder);
    $ftp_contents = ftp_nlist($conn_id, "-l" );
    # Gets the name of the VA directory 
    sort($ftp_contents);
    foreach ($ftp_contents as $v) {
        if (preg_match("/FalconStor-${VA}-$build_version-$build_number-.*/", $v, $out)) {
          $vacdfolder  = $out[0];
        }        
    }
    # Get the full ftp path to the build
    $qastaging_vacdfolder ="$qastaging_vacdfolder/$vacdfolder";
    # Gets the zip file from the Virtual Appliance folder
    ftp_chdir($conn_id, $vacdfolder);
    $ftp_contents = ftp_nlist($conn_id, "-l" );
    sort($ftp_contents);
    foreach ($ftp_contents as $v) {
        if (preg_match("/$vacdfolder.zip$/", $v, $out)) {
            # Will put in place system to check file against the configh.xml file
            # If xml entry is different will update with new name
            $vazipfile = $out[0];            
        }
    }
	print_and_log("Closing FTP Connection", "post_qastaging");
    // close the FTP stream 
    ftp_close($conn_id);
    print_and_log("The latest build is $vazipfile", "post_qastaging");
}

# Function downloads the va zip file from the FTP location
function build_download ($vatype) {
    global $qastaging_server;
    global $qastaging_user_name;
    global $qastaging_subfolder;
    global $qastaging_password;
    global $qastaging_vacdfolder;
    global $vazipfile;

    build_list($vatype);
    # Login to FTP server
	print_and_log("Connecting to $qastaging_server via FTP", "post_qastaging");
    $conn_id = ftp_connect($qastaging_server); 
    $login_result = ftp_login($conn_id, $qastaging_user_name, $qastaging_password); 

    if ((!$conn_id) || (!$login_result)) { 
        echo_and_log("ERROR: FTP connection has failed! Attempted to connect to $ftp_server for user $ftp_user_name", "post_qastaging"); 
    } else {
        echo_and_log("Connected to $qastaging_server, for user $qastaging_user_name", "post_qastaging");
    }
    # Change to dir of build and download
	print_and_log("Getting $qastaging_vacdfolder/$vazipfile", "post_qastaging");
    ftp_chdir($conn_id, $qastaging_vacdfolder);
    $download = ftp_get($conn_id, $vazipfile, $vazipfile, FTP_BINARY);
    if (!$download) { 
        echo_and_log("ERROR: FTP Download has failed!", "post_qastaging");
    } else {
        echo_and_log("Downloaded $vazipfile to $vazipfile", "post_qastaging");
    }

	print_and_log("Closing FTP Connection", "post_qastaging");
    // close the FTP stream 
    ftp_close($conn_id);
    build_unzip($vazipfile);
}

function build_unzip($vafile) {

    $local_path = $vafile;
    print_and_log ("Unzip $local_path", "post_qastaging");
    LogSystem("unzip $local_path");
}

# Function installs the build
function build_install() {
    global  $vcenter;
    global  $vcenter_user;
    global  $vcenter_password;
    global  $esxserver;
    global  $vaname;
    global  $datastore;
    global  $tgtnetwork1;
    global  $tgtnetwork2;
    
    print_and_log("Installing VA ($vaname) to $vcenter", "post_qastaging");
    # Runs ovftool to deploy and poweron VA.  Ovftool command will infor user of any errors
    $result = LogSystem("/usr/bin/ovftool  --acceptAllEulas --powerOn -ds=" . $datastore . " -n=" . $vaname . " --net:\"VM Network\"="     . $tgtnetwork1     . " --net:\"VM Network1\"=" . $tgtnetwork1 . " FalconStor-CDPVA-vHW7/FalconStor-VA/FalconStor-CDPVA.ovf vi://" . $vcenter_user . ":\\" . $vcenter_password . "@" . $vcenter . "/?ip=" . $esxserver . "");

    $result_output = $result->GetOutput();
    foreach($result_output as $output_line) {
    echo $output_line . "\n";
    }
}

function build_qualify() {
	global $qastaging_server;
	
	$pre_sep = "";
	$qualify_sep = "";

    $config = new Config();
	# get qualification machine details
	$qualify_config = $config->GetQualificationMachine();
    $latest_build = $config->GetLatest();
    
    $pre_script = $qualify_config['prescript'];
    $pre_script_params = $qualify_config['prescriptparams'];
    $pre_local = $qualify_config['prescriptlocal'];
    $qualify_script = $qualify_config['qualificationscript'];
    $qualify_script_params = $qualify_config['qualificationscriptparams'];
    $local = $qualify_config['qualificationscriptlocal'];

    if ($qualify_config['name'] != '') {        
        $qualify_machine = new ServerBuild($qualify_config['name'], $qualify_config['username'], $qualify_config['password'], $qualify_config['bluestone-username'], $qualify_config['bluestone-password']);
    
        print_and_log("Qualifying the Build " . $latest_build['build'] . " on " . $qualify_config['name'], "build_qualify");
    	echo "\n";
    
    	if ($pre_script != "") {
        	if (strtolower($pre_local) == "local") {
        		print_and_log("Executing Pre-Qualification Script, " . $pre_script . ", locally on this machine", "build_qualify");
        		if (substr($pre_script, 0, 1) != "/") { $pre_sep = "./"; }
        		echo "DEBUG: \"" . $pre_script_params . "\"\n";
        		$result = LogSystem($pre_sep . $pre_script . " " . $pre_script_params);
            	foreach($result->GetOutput() as $output_line) {
            		echo $output_line . "\n";
            	}
        		if ($result->GetErrorCode() != 0) {
            		echo_and_log("ERROR: Pre-Qualification Script did not run successfully. Error Code: " . $result->GetErrorCode() . ". STD_ERR output follows:", "build_qualify");
        		  	foreach($result->GetErrorOutput() as $error_line) {
    		            echo $error_line . "\n";
    	            }
        		}
        	} else {
        		print_and_log("Executing Pre-Qualification Script, " . $pre_script . ", on the qualification machine " . $qualify_config['name'], "build_qualify");
        		$result = $qualify_machine->RunScript($pre_script, $pre_script_params);
        	}	    
        } else {
        	print_and_log("No Pre-Qualification Script is saved in the configuration. Therefore none will be executed.");
        }
    
    	# push build to qualification machine
    	print_and_log("Pushing the build " . $latest_build['build'] . "(" . $latest_build['filename'] . ") to the machine " . $qualify_config['name'], "build_qualify");
    	$qualify_machine->PushBuild($latest_build['filename'], $latest_build['filename']);
    	# purge uninstall the build from the machine
    	print_and_log("Uninstalling (with database removal) the existing copy of Bluestone on the machine " . $qualify_config['name'], "build_qualify");
        $qualify_machine->RemoveBuild(True);
    	# install the new build on the machine
    	print_and_log("Installing Bluestone on the machine " . $qualify_config['name'], "build_qualify");
        $install_result = $qualify_machine->InstallBuild($latest_build['filename']);
    
        if ($install_result->GetErrorCode() == 0) {
        	# run a qualify script of some sort
        	# get the result
        	if (strtolower($local) == "local") {
        		print_and_log("Running Build Qualification Process: " . $qualify_script . " locally on this machine", "build_qualify");
           		if (substr($qualify_script, 0, 1) != "/") { $qualify_sep = "./"; }
        		$qualify_result = LogSystem($qualify_sep . $qualify_script . " " . $qualify_script_params);
             	foreach($qualify_result->GetOutput() as $output_line) {
            		echo $output_line . "\n";
            	}
           		if ($qualify_result->GetErrorCode() != 0) {
            		echo_and_log("ERROR: Qualification Script did not run successfully. Error Code: " . $result->GetErrorCode() . ". STD_ERR output follows:", "build_qualify");
           		    foreach($qualify_result->GetErrorOutput() as $error_line) {
        		        echo $error_line . "\n";
        	        }
           		}
        	} else {
        		print_and_log("Running Build Qualification Process: " . $qualify_script . " on the qualification machine " . $qualify_config['name'], "build_qualify");
        		$qualify_result = $qualify_machine->RunScript($qualify_script, $qualify_script_params);
        	}
        
        	if ($qualify_result->GetErrorCode() == 0) {
        		# if the result was passed update the qualified field in latest to say "pass"
        		print_and_log("Build Qualification PASSED. Updating configuration XML and uploading the build to $qastaging_server", "build_qualify");
        		post_qastaging($latest_build['filename']);

        		$config->UpdateLatest("", "", "", "Pass");
        	} else {
        		# if the result was failed, update the qualified field in latest to say "fail"	
        		print_and_log("Build Qualification FAILED. Updating configuration XML", "build_qualify");
        		$config->UpdateLatest("", "", "", "Fail");
        	}
        } else {
            print_and_log("ERROR: Installation to the qualification server was not successful. Error Code: " . $install_result->GetErrorCode(), "build_qualify");
            print_and_log("The Qualification Script will not be run. Qualification will be labeled as failed.", "build_qualify");
            $config->UpdateLatest("", "", "", "Fail");
        }
    } else {
        print_and_log("ERROR: No Qualification Server has been saved in the configuration. Build qualification on the build will not take place", "build_qualify");
    }
}

function qualifyserver_list($long = False) {
    $config = new Config();
	$qualify_config = $config->GetQualificationMachine();

	if ($qualify_config['modified'] != "") {
		$modified_readable = date(DATE_COOKIE, $qualify_config['modified']);
	} else {
		$modified_readable = "";
	}

	print_and_log("The currently saved configuration for the qualification machine, is as follows:", "qualifyserver_list");
	echo "\n";
	
	if ($long == False) {
		echo "Machine Name,System Username,System Password,Bluestone Username,Bluestone Password,Build,Modified Date Epoch,Modified Date Epoch,Modified Date,Pre-Qualification Script File Path,Pre-Qualification Script Parameters,Pre-Qualification Script Local,Qualification Script File Path,Qualification Script Parameters,Qualification Script Local\n";
		echo $qualify_config['name'] . "," . $qualify_config['username'] . "," .
		     $qualify_config['password'] . "," . $qualify_config['bluestone-username'] . "," .
		     $qualify_config['bluestone-password'] . "," .
		     $qualify_config['drac-address'] . "," . $qualify_config['drac-username'] . "," .
		     $qualify_config['drac-password'] . "," . $qualify_config['build'] . "," .
		     $qualify_config['modified'] . "," . $modified_readable . "," . 
		     $qualify_config['prescript'] . "," . $qualify_config['prescriptparams'] . "," .
		     $qualify_config['prescriptlocal'] . "," . $qualify_config['qualificationscript'] . "," .
		     $qualify_config['qualificationscriptparams'] . "," . $qualify_config['qualificationscriptlocal'] . "\n";
	} else {
	echo "Machine Name=" . $qualify_config['name'] . "\n" .
	     "System Username=" . $qualify_config['username'] . "\n" .
	     "System Password=" . $qualify_config['password'] . "\n" .
	     "Bluestone Username=" . $qualify_config['bluestone-username'] . "\n" .
	     "Bluestone Password=" . $qualify_config['bluestone-password'] . "\n" .
	     "Build Number=" . $qualify_config['build'] . "\n" .
	     "Modified Date Epoch=" . $qualify_config['modified'] . "\n" . 
         "Modified Date=" . $modified_readable . "\n" . 
	     "Pre-Qualification Script File Path=" . $qualify_config['prescript'] . "\n" . 
	     "Pre-Qualification Script Parameters=" . $qualify_config['prescriptparams'] . "\n" . 
	     "Pre-Qualification Script Locally Launched?=" . $qualify_config['prescriptlocal'] . "\n" . 
	     "Qualification Script File Path=" . $qualify_config['qualificationscript'] . "\n" . 
	     "Qualification Script Parameters=" . $qualify_config['qualificationscriptparams'] . "\n" . 
	     "Qualification Script Locally Launched?=" . $qualify_config['qualificationscriptlocal'] . "\n";
	}
}

function qualifyserver_update($name = "", $username = "", $password = "",
                              $bluestone_username = "", $bluestone_password = "",
                              $pre_script = "", $pre_params = "", $pre_local = "Local",
                              $qualify_script = "", $qualify_params = "", $qualify_local = "Remote",
                              $build = "", $modified = "") {
    $config = new Config();
	print_and_log("Updating the Qualification Server Settings in Configuration XML", "qualifyserver_update");
	$config->UpdateQualificationMachine($name, $username, $password, $bluestone_username, $bluestone_password,
	                                    $pre_script, $pre_params, $pre_local, $qualify_script, $qualify_params,
	                                    $qualify_local, $build, $modified);
}


function server_add($name, $username, $password, $bluestone_username, $bluestone_password) {
    $config = new Config();
    $machine = new ServerBuild($name, $username, $password, $bluestone_username, $bluestone_password);
    
    $current_version = $machine->GetVersion();
    print_and_log("Adding Server with the following details:", "server_add");
    print_and_log("Machine Name: " . $name, "server_add");
    print_and_log("System Username: " . $username, "server_add");
    print_and_log("System Password: " . $password, "server_add");
    print_and_log("Bluestone Username: " . $bluestone_username, "server_add");
    print_and_log("Bluestone Password: " . $bluestone_password, "server_add");
    if ($current_version['error_code'] == 0) {
 	    print_and_log("Build (queried from machine): " . $current_version['build'], "server_add");
	    $config->AddMachine($name, $username, $password, $bluestone_username, $bluestone_password, $current_version['build'], "");
    } else {
	    $config->AddMachine($name, $username, $password, $bluestone_username, $bluestone_password, "", "");
    }
}

function server_list($name, $long = False) {
    if ($name == "") {
        echo_and_log("No machine name was specified. Please specify a machine name with the -s option", "server_list");
    } else {
        $config = new Config();
        # TODO: Need to throw an exception here
    	$machine_details = $config->GetMachine($name);

    	if ($machine_details['modified'] != "") {
    		$modified_readable = date(DATE_COOKIE, $machine_details['modified']);
    	} else {
    		$modified_readable = "";
    	}

    	print_and_log("The currently saved configuration for the machine, " . $name . " is as follows", "server_list");
    	echo "\n";
    	
    	if ($long == False) {
    		echo "Machine Name,Username,Password,Bluestone Username,Bluestone Password,Build,Modified Date Epoch,Modified Date,Filename\n";
    		echo $name . "," . $machine_details['username'] . "," . $machine_details['password'] . "," . $machine_details['bluestone-username'] . "," . $machine_details['bluestone-password'] . "," . $machine_details['build'] . "," . $machine_details['modified'] . "," . $modified_readable . "," . $machine_details['filename'] . "\n";
    	} else {
        	echo "Machine Name=" . $name . "\n" .
        	     "System Username=" . $machine_details['username'] . "\n" .
        	     "System Password=" . $machine_details['password'] . "\n" .
        	     "Bluestone Username=" . $machine_details['bluestone-username'] . "\n" .
        	     "Bluestone Password=" . $machine_details['bluestone-password'] . "\n" .
        	     "Build Number=" . $machine_details['build'] . "\n" .
        	     "Modified Date Epoch=" . $machine_details['modified'] . "\n" . 
                 "Modified Date=" . $modified_readable . 
                 "Filename=" . $machine_details['filename'] . "\n";
    	}
    }
}

function server_update($name, $username = "", $password = "", $bluestone_username = "", $bluestone_password = "", $new_name = "") {
    $config = new Config();

    print_and_log("Updating the machine " . $name . " with the following the information", "server_update");
	if ($username != "") { print_and_log("System Username: " . $username, "server_update"); }
	if ($password != "") { print_and_log("System Password: " . $password, "server_update"); }
	if ($bluestone_username != "") { print_and_log("Bluestone Username: " . $bluestone_username, "server_update"); }
	if ($bluestone_password != "") { print_and_log("Bluestone Password: " . $bluestone_password, "server_update"); }
	if ($new_name != "") { print_and_log("New Machine Name: " . $new_name, "server_update"); }
    $config->UpdateMachine($name, $username, $password, $bluestone_username, $bluestone_password, "", "", "", $new_name);
}

function server_remove($name) {
    $config = new Config();

    print_and_log("Deleting the machine " . $name, "server_remove");

    $config->DeleteMachine($name);
}

function print_usage() {
	global $program_name;

	echo "Usage:\n";
	echo "\tphp " . $program_name . " -o help -c <command> [NOT IMPLEMENTED]\n";
	echo "\tphp " . $program_name . " -o <command> <parameters>\n\n";
	echo "Commands:\n\n";
	echo "Managing Latest Cached Build and its Configuration:\n";
	echo "\tbuild-download - Downloads the latest available build\n";
	echo "\tbuild-install - Installs the VA on the vCenter\n";
	echo "\tbuild-list - Lists the current saved configuration for the latest build\n";
	echo "\tbuild-qualify - Qualifies the current build against the saved qualification server\n\n";
	echo "Managing Server Configuration Information:\n";
	echo "\tserver-list - Lists the configuration information for the specified server\n";
	echo "\tserver-listall - Lists the configuration information for all the saved servers [NOT IMPLEMENTED]\n";
	echo "\tserver-add - Adds a server to the config.xml configuration file\n";
	echo "\tserver-update - Updates the configuration information for a server\n";
	echo "\tserver-remove - Removes a server from the config.xml configuration file\n\n";
	echo "Managing the Build Qualification Server Configuration Information:\n";
	echo "\tqualifyserver-list - Lists the configuration information for the qualification server\n";
	echo "\tqualifyserver-update - Updates the configuration information for the qualification server\n";
	echo "\tqualifyserver-remove - Removes the qualification server's config from the config.xml\n\n";
	echo "Managing Builds on Configured Servers:\n";
	echo "\tserverbuild-list - Queries the server to display the currently installed build\n";
}

$operation = '';
$user_machine = '';
$user_username = '';
$user_password = '';
$user_bluestone_username = '';
$user_bluestone_password = '';
$user_new_machinename = '';
$user_qualify_script = '';
$user_qualify_script_params = '';
$user_pre_script = '';
$user_pre_script_params = '';
$va_type = '';
$va_file = '';
$user_va_type = '';
$user_purge = False;
$user_force = False;
$long_listing = False;
$user_local_script = "Remote";
$user_pre_local_script = "Local";
$program_status = 0;

$options = getopt('o:s:u:p:n:w:e:q:m:t:a:v:f:lrfcibd');

foreach ($options as $param => $value) {
    if ($param == 'o') {
        $operation = $value;
    } elseif ($param == 's') {
    	$user_machine = $value;
    } elseif ($param == 'u') {
    	$user_username = $value;
    } elseif ($param == 'p') {
    	$user_password = $value;
    } elseif ($param == 'n') {
    	$user_bluestone_username = $value;
    } elseif ($param == 'w') {
    	$user_bluestone_password = $value;
    } elseif ($param == 'e') {
    	$user_new_machinename = $value;
    } elseif ($param == 'q') {
    	$user_qualify_script = $value;
    } elseif ($param == 'm') {
    	$user_qualify_script_params = $value;
    } elseif ($param == 't') {
    	$user_pre_script = $value;
    } elseif ($param == 'f') {
        $va_file = $value;
    } elseif ($param == 'a') {
    	$user_pre_script_params = $value;
    } elseif  ($param == 'v') {
        $va_type = $value;
    } elseif ($param == 'l') {
    	$long_listing = True;
    } elseif ($param == 'r') {
    	$user_purge = True;
    } elseif ($param == 'f') {
    	$user_force = True;
    } elseif ($param == 'c') {
    	$user_local_script = "Local";
    } elseif ($param == 'i') {
    	$user_pre_local_script = "Local";
    } elseif ($param == 'b') {
    	$user_local_script = "Remote";
    } elseif ($param == 'd') {
    	$user_pre_local_script = "Remote";
    }
}

PrintHeader();

#TODO: Error correction for missing command line arguments
switch ($operation) {
    case "build-download":
        build_download($va_type);
        break;
    case "build-install":
        build_install();
        break;
    case "build-list":
        build_list($va_type);
        break;
    case "build-qualify":
        build_qualify();
        break;
    case "server-add":
        server_add($user_machine, $user_username, $user_password, $user_bluestone_username,
                   $user_bluestone_password);
        break;
    case "server-list":
        server_list($user_machine, $long_listing);
        break;
    case "server-update":
        server_update($user_machine, $user_username, $user_password, $user_bluestone_username,
                      $user_bluestone_password, $user_new_machinename);
        break;
    case "server-remove":
        server_remove($user_machine);
        break;
    case "qualifyserver-list":
        qualifyserver_list($long_listing);
        break;
    case "qualifyserver-update":
        qualifyserver_update($user_machine, $user_username, $user_password,
                             $user_bluestone_username, $user_bluestone_password,
                             $user_pre_script, $user_pre_script_params, $user_pre_local_script,
                             $user_qualify_script, $user_qualify_script_params, $user_local_script,
                             "", "");
        break;
    case "qualifyserver-remove":
        qualifyserver_remove();
        break;
    case "serverbuild-list":
        serverbuild_list($user_machine, $long_listing);
        break;
    case "serverbuild-update":
        serverbuild_update($user_machine, $user_purge, $user_force);
        break;
    case "serverbuild-install":
        serverbuild_install($user_machine, $user_force);
        break;
    case "serverbuild-remove":
        serverbuild_remove($user_machine, $user_purge);
        break;
    case "serverbuild-refresh":
        serverbuild_refresh($user_machine);
        break;
    case "build-unzip":
        build_unzip($va_file);
        break;
    default:
    	print_usage();
}

PrintTailer("Operation Complete");
ExitProper($program_status);

//apps/vm/vmregister.pl --operation=unregister --server=192.168.15.91 --username=primevault --password=\$angbe123 --vmname=NSSVA-CH03


//echo_and_log("Turning " . $vmname . " on...", "resetvm.php");
//$result = LogSystem("/usr/lib/vmware-vcli/apps/vm/vmcontrol.pl --operation=" . $controlop .
//                     " --username=" . $username . " --password=" . $password .
//                     "  --server=" . $server . " --vmname=\"" . $vmname . "\"");

