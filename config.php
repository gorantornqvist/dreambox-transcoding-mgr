;<?php
;die("This file may not be accessed directly"); 
;/*
; Dreambox Transcoding Manager config file

[global]

; Website title
title = "Dreambox Transcoding Manager"

; Our external hostname
webaddress = "webtv.yourdomain.com"

; Directory to store logfiles and data files
; Make sure the webserver user has write access to it
datadir = "/var/www/vhosts/data"

; Logfile for Dreambox Transcoding Manager events - stored in datadir
logfilename = "webtv.log" 

; The file we use to store IP addresses allowed to access the transcoding ports
; Make sure the webserver user owns it
clientsfile = "/var/www/vhosts/data/iptables-vlc-clients"

; In reverse proxy mode we use HTTP_X_FORWARDED_FOR header instead of REMOTE_ADDR
; Make sure your reverse proxy config passes this header if you use it
reverse_proxy_mode = "false"

; Absolute path to taskset program
taskset = /bin/taskset

; Absolute path to vlc program
vlc = /usr/bin/vlc 

; VLC Transcode options for different qualities
quality['low'] = "vcodec=mp4v, vb=128, acodec=mp4a, ab=64, scale=0.50"
quality['normal'] = "vcodec=mp4v, vb=512, acodec=mp4a, ab=96, scale=0.70"
quality['high'] = "vcodec=mp4v, vb=1024, acodec=mp4a, ab=128"

[dreambox1]
description = "Dreambox Bedroom"
// Either 1 or 2
enigmaversion = 1
ipaddress = "192.168.1.18"
// Local port to use when transcoding
port = 12345
// CPU Affinity - Which CPU ID the transcoding process should run on. 0 = First CPU
cpu = 0
// Only needed for Enigma v1 dreamboxes. Specifies the name of the User bouquet to use when listing channels
bouquet = "Favoriter (TV)"

[dreambox2]
description = "Dreambox Study"
enigmaversion = 1
ipaddress = "192.168.1.19"
port = 23456
cpu = 1
// Only for Enigma v1 dreamboxes. Specifies the name of the User bouquet to use when listing channels
bouquet = "Favoriter (TV)"

[dreambox3]
description = "Dreambox Livingroom"
enigmaversion = 2
ipaddress = "192.168.1.17"
port = 8080
cpu = 2
// This is our main dreambox so only the users "me" and "mywife" is allowed to operate it
operators[] = "me"
operators[] = "mywife"

;*/
;?>
