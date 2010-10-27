<?php
    /*
       Simple solarmax visualizer php program written by zagibu@gmx.ch in July 2010
       This program was originally licensed under WTFPL 2 http://sam.zoy.org/wtfpl/
       Improvements by Frank Lassowski flassowski@gmx.de in August 2010
       This program is now licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html
    */
   $title="bierderlog";
   $slogan="unsere Photovoltaik-Anlage";
   $link1="http://lassowski.dyndns.org/BierderLOG/";
   $link2="http://lassowski.dyndns.org/BierderLOG/?e=22";

   echo "<div id=\"header\">\n";
   echo "	<h1><a href=\"" . $link1 . "\">" . $title . "</a></h1>\n";
   echo "	<h5><a href=\"" . $link2 . "\">" . $slogan . "</a></h5>\n";
   echo "</div>\n";
?>