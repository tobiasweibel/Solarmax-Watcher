<?php
   /*
      PHP data feeder for SolarAnalyzer ( http://sunics.de/solaranalyzer_beschreibung.htm written by Stephan Collet stephan@sunics.de )
      written by Frank Lassowski flassowski@gmx.de in August 2010
      licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html

      Put this file in your web directory, for example /var/www/
      It will be automagically called by SolarAnalyzer like this:
      http://yourwebadress/analyzer.php?wr=1&q=day&d=3&m=9&y=2010   data of 2010-09-03 from inverter 1
      or
      http://yourwebadress/analyzer.php?wr=2&q=allDayData           data of all days from inverter 2
      
      You shouldn't change the file name otherwise SolarAnalyzer can't figure out
      where to find it.
   */

   // select table by page query ?wr=
   $wr=$_GET['wr'];
   $table="log{$wr}";

   // Check GET vars
   $q = $_GET['q'];

   // Daten aller Tage
   if ($q == "allDayData")
   {
      // Connect to mysql database
      @mysql_connect('localhost', 'user', 'password') or die(mysql_error());
      @mysql_select_db('solarmax') or die(mysql_error());
      $result = @mysql_query("SELECT created, DAY(created) AS day, max(kdy) AS kdy FROM $table GROUP BY DAYOFYEAR(created)") or die(mysql_error());

      if (mysql_num_rows($result) == 0)
         {
            // No data...create dummy page
            return 'no data';
         }
         echo "created;kdy_1\n";
         while($row = mysql_fetch_assoc($result)) {
            echo substr ($row['created'], 0, 10), ";";
            echo $row['kdy']*100, "\n";
         }
   }

   // Tagesdaten
   elseif ($q == "day")
   {
      $sday = $_GET['d'];
      if (empty($sday))
         $sday = date('j');
      $smonth = $_GET['m'];
      if (empty($smonth))
         $smonth = date('n');
      $syear = $_GET['y'];
      if (empty($syear))
         $syear = date('Y');

      $start['day'] = $sday;
      $start['month'] = $smonth;
      $start['year'] = $syear;
      $end['day'] = $sday;
      $end['month'] = $smonth;
      $end['year'] = $syear;

      // Make sure we define a valid end date
      while (!checkdate($end['month'], $end['day'], $end['year']))
         $end['day']--;

      // Include time in start and end delimiters
      $start = date('Y-m-d H:i:s', mktime(0, 0, 0, $start['month'], $start['day'], $start['year']));
      $end = date('Y-m-d H:i:s', mktime(23, 59, 59, $end['month'], $end['day'], $end['year']));

      // Connect to mysql database
      @mysql_connect('localhost', 'user', 'password') or die(mysql_error());
      @mysql_select_db('solarmax') or die(mysql_error());

      // Select data from given day
      $result = @mysql_query("SELECT * FROM $table WHERE created BETWEEN '$start' AND '$end'") or die(mysql_error());

      // No data...create dummy page      
      if (mysql_num_rows($result) == 0)
      {
         return 'no data';
      }

      echo "created;kdy_1;kmt_1;kyr_1;kt0_1;tnf_1;tkk_1;pac_1;prl_1;il1_1;idc_1;ul1_1;udc_1;sys_1\n";

      while($row = mysql_fetch_object($result))
      {
         echo $row->created, ";";
         echo $row->kdy*100, ";";
         echo $row->kmt, ";";
         echo $row->kyr, ";";
         echo $row->kt0, ";";
         echo $row->tnf/100, ";";
         echo $row->tkk, ";";
         echo $row->pac, ";";
         echo $row->prl, ";";
         echo $row->il1/100, ";";
         echo $row->idc/100, ";";
         echo $row->ul1/10, ";";
         echo $row->udc/10, ";";
         echo $row->sys, "\n";
      }
   }
?>
