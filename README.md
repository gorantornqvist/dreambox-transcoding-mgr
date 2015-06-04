# Dreambox Transcoding Manager

A web based management tool written in PHP that allows you to start VLC transcoding processes on a linux box that streams the video from Enigma 1 and Enigma 2 dreamboxes in realtime to a quality of your choosing. You can then access these streams using the VLC program and other video streaming clients.

It´s been tested on CentOS 6 with selinux enabled using vlc 2.0.7 package from linuxtech repo and apache. Tested against both Enigma 1 and Enigma 2 dreamboxes. I have only done limited testing so consider this as a beta.

Feel free to report any issues you have with installing or using it ...

PS: If you are a web designer and want to contribute with making the interface look nicer, feel free to send me an updated index.php and I will update the pages and add your name as a contributor.

SETUP INSTRUCTIONS:
* Copy index.php and config.php to the webroot of your linux box.
* Create the directory "preview" in the same directory. It should have permissions that allow the webserver user to write to it.
* Edit the global options in config.php and add your dreamboxes. Follow the instructions in the comments.
* OPTIONAL: If you don´t want to waste CPU on your linux box you can add a cronjob that kills the processes after a while. Create /etc/cron.d/kill-vlc-sessions cronjob and make it run every 5 minute. Point it to kill-vlc-sessions.sh. Edit kill-vlc-sessions.sh for your needs. See kill-vlc-sessions.sh.sample
* STRONGLY SUGGESTED: Password protect the website, apache example:
<code>

   &lt;Directory "/var/www/vhosts/webtv.yourdomain.com"&gt;

      AuthType Basic
      
      AuthName "Authentication Required"
      
      AuthUserFile "/var/www/vhosts/data/webtv.yourdomain.com.htpasswd"
      
      Require valid-user
      
      Order allow,deny
      
      Allow from all
      
    &lt;/Directory&gt;
</code>
* Create the htpasswd file: htpasswd -c /var/www/vhosts/data/webtv.yourdomain.com.htpasswd yourusername yourpassword

SELINUX CONFIG:
* httpd_can_network_connect needs to be on, if not use setsebool to enable it.

<code>
getsebool -a | grep "httpd_can_network_connect "

httpd_can_network_connect --> on
</code>
* Apache/vlc needs to be able to bind to the streaming ports you specify in config.php.

<code>
semanage port -l | grep '^http_port_t'

http_port_t                    tcp      80, 81, 443, 488, 8008, 8009, 8443, 9000
</code>

Add your streaming port using command:

<code>
semanage port -a -t http_port_t -p tcp &lt;yourport&gt;
</code>

If you use a port that already exists in another selinux policy and above command complains about it, change -a to -m in above command.

