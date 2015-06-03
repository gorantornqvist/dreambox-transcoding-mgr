# dreambox-transcoding-mgr
Dreambox Transcoding Manager

A web based management tool written in PHP that allows you to start VLC transcoding streams on a linux box that streams the video from Enigma 1 and Enigma 2 dreamboxes in realtime. You can then access these streams from the internet using the VLC program and other video streaming clients.
It has built-in handling for adding the remote IP address that you are connecting from to the local iptables config thus allowing it to connect to streaming ports.

It´s been tested on CentOS 6 with selinux enabled using vlc 2.0.7 package from linuxtech repo and apache. Tested using both Enigma 1 and Enigma 2 dreamboxes.

Feel free to report and issues you have with installing or using it ...

SETUP INSTRUCTIONS:
* Copy index.php and config.php to the webroot of your linux box.
* Create the directory "preview" in the same directory. It should have permissions that allow the webserver user to write to it.
* Edit the global options in config.php and add your dreamboxes. Follow the instructions in the comments.
* Create /etc/sysconfig/iptables according to iptables.config.sample - modify it and add your streaming ports.
* Create /etc/cron.d/iptables-vlc-clients cronjob and make it run every minute. Point it to iptables-vlc-clients.sh. Edit iptables-vlc-clients.sh for your needs.
* Create /etc/cron.d/kill-vlc-sessions cronjob and make it run every 5 minute. Point it to kill-vlc-sessions.sh. Edit kill-vlc-sessions.sh for your needs.
* Password protect the website, apache example:
<code>
   <Directory "/var/www/vhosts/webtv.yourdomain.com">
      AuthType Basic
      AuthName "Authentication Required"
      AuthUserFile "/var/www/vhosts/data/webtv.yourdomain.com.htpasswd"
      Require valid-user
      Order allow,deny
      Allow from all
    </Directory>
</code>
* Create the htpasswd file: htpasswd -c /var/www/vhosts/data/webtv.yourdomain.com.htpasswd yourusername yourpassword

SELINUX CONFIG:
* httpd_can_network_connect needs to be on, if not use setsebool to enable it.

<code>
getsebool -a | grep "httpd_can_network_connect "

httpd_can_network_connect --> on
</code>
* Apache needs to be able to bind to the streaming ports you specify in config.php.

<code>
semanage port -l | grep '^http_port_t'

http_port_t                    tcp      80, 81, 443, 488, 8008, 8009, 8443, 9000
</code>

Add your streaming port using command:

<code>
semanage port -a -t http_port_t -p tcp <yourport>
</code>

If you use a port that already exists in another policy and above command complains about it, change -a to -m in above command.

