# Dynamic DNS client for netcup DNS API
**A dynamic DNS client written in PHP for use with the netcup DNS API.** This project is a fork of https://github.com/stecklars/dynamic-dns-netcup-api.

## Docker
Please refer to the dockernized version under https://hub.docker.com/r/mm28ajos/docker-dynamic-dns-netcup-api.

## Features
* Determines public IP addresses (IPv4 and IPv6) without external third party look ups.
    * using local adapter for IPv6
    * using local FritzBox for IPv4. Note, using external service for determining the IPv4 addresses is possible if no fritz box is available or as a fallback
* Caching the IP provided to netcup DNS to avoid unnecessary API calls
* Updating of a specific or multiple subdomains or domain root
* E-Mail alert in case updating/getting new IP addresses runs in warnings/errors
* configure hosts for updating IPv4 and IPv6 separately
* Creation of DNS record if it does not already exist for the subdomain given
* If configured, lowers TTL to 300 seconds for the domain on each run if necessary
* Restart docker containers on IP address change if configured

## Requirements
* Be a netcup customer: https://www.netcup.de – or for international customers: https://www.netcup.eu
* You don't have to be a domain reseller to use the necessary functions for this client – every customer with a domain may use it.
* netcup API key and API password, which can be created within your CCP at https://ccp.netcup.net
* A domain :wink:

## Getting started
### Download
Download the [latest version](https://github.com/mm28ajos/dynamic-dns-netcup-api/releases/latest) from the releases or clone the repository:

`$ git clone https://github.com/mm28ajos/dynamic-dns-netcup-api`

Alternativly, use docker. Refer to https://hub.docker.com/r/mm28ajos/docker-dynamic-dns-netcup-api.

### Configuration
Configuration is very simple: Just fill out `config.ini` with the required values. The options are explained in there.

### How to use
`php update.php`

You should probably run this script every few minutes, so that your IP is updated as quickly as possible. Add it to your cronjobs and run it regularly, for example every five minutes.

### CLI options
Just add these options after the command like `./update.php --quiet`

| short option | long option        | function                                                  |
| ------------ | ------------------ |----------------------------------------------------------:|
| -q           | --quiet            | The script won't output notices, only errors and warnings |
| -c           | --config           | Manually provide a path to the config file                |
| -4           | --ipv4             | Manually provide the IPv4 address to set                  |
| -6           | --ipv6             | Manually provide the IPv6 address to set                  |
| -h           | --help             | Outputs this help                                         |
| -v           | --version          | Outputs the current version of the script                 |

If you have ideas on how to improve this script, please don't hesitate to create an issue or provide me with a pull request. Thank you!
