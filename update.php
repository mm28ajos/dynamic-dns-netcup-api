<?php

//Load necessary functions
require_once 'functions.php';

// check if curl is installed
if (! _is_curl_installed()) {
    outputStderr("cURL PHP extension is not installed. Please install the cURL PHP extension, otherwise the script will not work. Exiting.");
    exit(1);
}

//Constants
const SUCCESS = 'success';
const IP_CACHE_FILE = '/ipcache';
const VERSION = '4.5.1';
const USERAGENT = "dynamic-dns-netcup-api/" . VERSION ." (by mm28ajos)";
const APIURL = 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON';

//Check passed options
$shortopts = "q4:6:c:vh";
$longopts = array(
    "quiet",
    "ipv4:",
    "ipv6:",
    "config:",
    "version",
    "help"
);

$options = getopt($shortopts, $longopts);
if (isset($options['version']) || isset($options['v'])) {
    echo "Dynamic DNS client for netcup ".VERSION." (by mm28ajos)\n";
    echo "This script is not affiliated with netcup.\n";
    exit();
}
if (isset($options['help']) || isset($options['h'])) {
    echo "\n";
    echo "Dynamic DNS client for netcup ".VERSION." (by mm28ajos)\n";
    echo "This script is not affiliated with netcup.\n";
    echo "\n| short option | long option        | function                                                  |
| ------------ | ------------------ |----------------------------------------------------------:|
| -q           | --quiet            | The script won't output notices, only errors and warnings |
| -c           | --config           | Manually provide a path to the config file                |
| -4           | --ipv4             | Manually provide the IPv4 address to set                  |
| -6           | --ipv6             | Manually provide the IPv6 address to set                  |
| -h           | --help             | Outputs this help                                         |
| -v           | --version          | Outputs the current version of the script                 |\n\n";
    exit();
}

if (isset($options['config']) || isset($options['c'])) {
    $configFilePath = isset($options['c']) ? $options['c'] : $options['config'];
} else {
    // If user does not supply an option on the CLI, we will use the default location.
    $configFilePath = __DIR__ . '/config.ini';
}

// load config file
if (file_exists($configFilePath)) {
    $config_array = parse_ini_file($configFilePath, false, true);
} else {
    outputStderr(sprintf('Could not open config.ini at "%s". Please follow the getting started guide and provide a valid config.ini file. Exiting.', $configFilePath));
    exit();
}

if (isset($options['quiet']) || isset($options['q'])) {
    $quiet = true;
}
if (isset($options['ipv4']) || isset($options[4])) {
    $providedIPv4 = isset($options[4]) ? $options[4] : $options["ipv4"];
    if (!isIPV4Valid($providedIPv4)) {
        outputStderr(sprintf('Manually provided IPv4 address "%s" is invalid. Exiting.', $providedIPv4));
        exit(1);
    }
}
if (isset($options['ipv6']) || isset($options[6])) {
    $providedIPv6 = isset($options[6]) ? $options[6] : $options["ipv6"];
    if (!isIPv6Valid($providedIPv6)) {
        outputStderr(sprintf('Manually provided IPv6 address "%s" is invalid. Exiting.', $providedIPv6));
        exit(1);
    }
}

outputStdout("=============================================");
outputStdout("Running dynamic DNS client for netcup ".VERSION." (by mm28ajos)");
outputStdout("This script is not affiliated with netcup.");
outputStdout("=============================================\n");

// get cached IP addresses
$ipcache = getIPCache();

// set default values
$ipv4change = false;
$ipv6change = false;

$publicIPv4 = '127.0.0.1';
$publicIPv6 = '::1';

if ($config_array['USE_IPV4'] === 'true') {
	// do some logging
	outputStdout(sprintf("Updating DNS records for host(s) '%s' (A record) on domain %s", $config_array['HOST_IPv4'], $config_array['DOMAIN']));

        // If user provided an IPv4 address manually as a CLI option
        global $providedIPv4;
        if (isset($providedIPv4)) {
            outputStdout(sprintf('Using manually provided IPv4 address "%s"', $providedIPv4));
            $publicIPv4 = $providedIPv4;
        } else {
 	   // get public IPv4 address
 	   $publicIPv4 = $config_array['USE_FRITZBOX']  === 'true' ? getCurrentPublicIPv4FromFritzBox($config_array['FRITZBOX_IP']) : getCurrentPublicIPv4();
        }

	if ($publicIPv4) {
		if ($ipcache !== false) {
			// check whether public IPv4 has changed according to IP cache
			if ($ipcache['ipv4'] !== $publicIPv4) {
				$ipv4change = true;
				outputStdout(sprintf("IPv4 address has changed according to local IP cache. Before: %s; Now: %s", $ipcache['ipv4'], $publicIPv4));
			} else {
				$ipv4change = false;
				outputStdout("IPv4 address hasn't changed according to local IP cache. Current IPv4 address: ".$publicIPv4);
			}
		} else {
			$ipv4change = true;
		}
	} else {
		$ipv4change = false;
	}
}

if ($config_array['USE_IPV6'] === 'true') {
        // do some logging
        outputStdout(sprintf("Updating DNS records for host(s) '%s' (AAAA record) on domain %s", $config_array['HOST_IPv6'], $config_array['DOMAIN']));

        // If user provided an IPv6 address manually as a CLI option
        global $providedIPv6;
        if (isset($providedIPv6)) {
            outputStdout(sprintf('Using manually provided IPv6 address "%s"', $providedIPv6));
            $publicIPv6 = $providedIPv6;
        } else {
	   // get public IPv6 address
	   $publicIPv6 = getCurrentPublicIPv6($config_array['IPV6_INTERFACE'], $config_array['NO_IPV6_PRIVACY_EXTENSIONS']);
        }

	if ($publicIPv6) {
		if ($ipcache !== false) {
			// check whether public IPv6 has changed according to IP cache
			if ($ipcache['ipv6'] !== $publicIPv6) {
				$ipv6change = true;
				outputStdout(sprintf("IPv6 address has changed according to local IP cache. Before: %s; Now: %s", $ipcache['ipv6'], $publicIPv6));
			} else {
				$ipv6change = false;
				outputStdout("IPv6 address hasn't changed according to local IP cache. Current IPv6 address: ".$publicIPv6);
			}
		} else {
			$ipv6change = true;
		}
	} else {
		$ipv6change = false;
	}
}

// Login to to netcup via API if public ipv4 or public ipv6 is available AND changes need to be updated
if ($ipv4change === true | $ipv6change === true) {

	// Login
	if ($apisessionid = login($config_array['CUSTOMERNR'], $config_array['APIKEY'], $config_array['APIPASSWORD'])) {
		outputStdout("Logged in successfully!");
	} else {
		// clear ip cache in order to reconnect to API in any case on next run of script
		clearIPCache();
		exit(1);
	}

	// Let's get infos about the DNS zone
	if ($infoDnsZone = infoDnsZone($config_array['DOMAIN'], $config_array['CUSTOMERNR'], $config_array['APIKEY'], $apisessionid)) {
		outputStdout("Successfully received Domain info.");
	} else {
		// clear ip cache in order to reconnect to API in any case on next run of script
		clearIPCache();
		exit(1);
	}

	//TTL Warning
	if ($config_array['CHANGE_TTL'] !== 'true' && $infoDnsZone['responsedata']['ttl'] > 300) {
		outputStdout("TTL is higher than 300 seconds - this is not optimal for dynamic DNS, since DNS updates will take a long time. Ideally, change TTL to lower value. You may set CHANGE_TTL to True in config.ini, in which case TTL will be set to 300 seconds automatically.");
	}

	//If user wants it, then we lower TTL, in case it doesn't have correct value
	if ($config_array['CHANGE_TTL'] === 'true' && $infoDnsZone['responsedata']['ttl'] !== "300") {
		$infoDnsZone['responsedata']['ttl'] = 300;

		if (updateDnsZone($config_array['DOMAIN'], $config_array['CUSTOMERNR'], $config_array['APIKEY'], $apisessionid, $infoDnsZone['responsedata'])) {
			outputStdout("Lowered TTL to 300 seconds successfully.");
		} else {
			outputStderr("Failed to set TTL... Continuing.");
		}
	}

	//Let's get the DNS record data.
	if ($infoDnsRecords = infoDnsRecords($config_array['DOMAIN'], $config_array['CUSTOMERNR'], $config_array['APIKEY'], $apisessionid)) {
		outputStdout("Successfully received DNS record data.");
	} else {
		// clear ip cache in order to reconnect to API in any case on next run of script
		clearIPCache();
		exit(1);
	}

	// update ipv4
	if ($ipv4change) {
		updateIP($infoDnsRecords, $publicIPv4, $apisessionid, $config_array['HOST_IPv4'], $config_array['HOST_IPv4'], $config_array['DOMAIN'], $config_array['CUSTOMERNR'], $config_array['APIKEY']);
	}

	// update ipv6
	if ($ipv6change) {
		updateIP($infoDnsRecords, $publicIPv6, $apisessionid, $config_array['HOST_IPv6'], $config_array['HOST_IPv4'], $config_array['DOMAIN'], $config_array['CUSTOMERNR'], $config_array['APIKEY']);
	}

	//Logout
	if (logout($config_array['CUSTOMERNR'], $config_array['APIKEY'], $apisessionid)) {
		outputStdout("Logged out successfully!");
	}

	// update ip cache
	setIPCache($publicIPv4, $publicIPv6);
}

// restart docker container(s) if IP changed and setting is activated
if (array_key_exists('RESTART_CONTAINERS', $config_array)) {
	if (($ipv6change === true | $ipv4change === true) && $config_array['RESTART_CONTAINERS'] === 'true') {
		shell_exec("docker restart ".str_replace(",", " ", $config_array['CONTAINERS']));
	}
}
?>
