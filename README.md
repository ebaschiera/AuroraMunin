# AuroraMunin
Munin plugin for ABB/Aurora Inverters.

It is written in PHP and it reads the output from [Aurora](http://www.curtronics.com/Solar/AuroraData.html) application.

## Requirements
* [Aurora](http://www.curtronics.com/Solar/AuroraData.html) application set to write output to a file (see Installation below)
* PHP installed

## Installation
* Install this script as a normal Munin plugin by chmodding it as executable and by symlinking it into `/etc/munin/plugins`
* create a configuration file called `/etc/munin/plugin-conf.d/aurora` and define the output path of Aurora:
```
[aurora]
env.aurora_output_file_path /home/ermanno/aurora/output.txt
 ```
 * configure a cronjob that runs Aurora application every minute with the following options:
 ```
 /home/ermanno/aurora-1.7.3/aurora -Y 2 -c -T -d 0 -e -a 2 --output-file=/home/ermanno/aurora/output.txt /dev/solar-inverter-usb
 ```