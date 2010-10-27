    <?php
    /*
       Simple solarmax visualizer php program written by zagibu@gmx.ch in July 2010
       This program was originally licensed under WTFPL 2 http://sam.zoy.org/wtfpl/
       Improvements by Frank Lassowski flassowski@gmx.de in August 2010
       This program is now licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html

       To run this program your server must have PHP and the gd extension enabled.
       Put this and all the other files contained in 'solarertrag.tar.gz'
       in your web directory, for example /var/www/ and call it with
       http://yourwebadress/solarertrag.php
    */
       // select table by page query ?wr=
       $wr=$_GET['wr'];
       $table="log{$wr}";

       // which language does the users browser prefer
       $lang=substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
       if ($lang <> "de" && $lang <> "en" && $lang <> "nl" && $lang <> "fr" && $lang <> "es" && $lang <> "it")
          $lang="en";

       // include language file
       include 'lang.php';
       
       // if we want to switch to seperate language files we have to use the following line instead
       //include 'lang_' . $lang . '.php';

       // Which font to use in the graphs
       // for Windows based servers look at C:/Windows/Fonts for appropriate fonts
       $fontfile="/usr/share/fonts/truetype/ttf-dejavu/DejaVuSansMono.ttf";
       
       // Check POST vars
       $period = $_POST['period'];
       if (!in_array($period, array('day', 'month', 'year')))
          $period = 'day';
       $day = $_POST['day'];
       if (empty($day))
          $day = date('j');
       $month = $_POST['month'];
       if (empty($month))
          $month = date('n');
       $year = $_POST['year'];
       if (empty($year))
          $year = date('Y');
       if (!preg_match("/[0-9]?[0-9]\.[0-9]?[0-9]\.[0-9][0-9][0-9][0-9]/", "$day.$month.$year"))
          die(${error1.$lang} . "($day, $month, $year)");
       if (!checkdate($month, $day, $year))
          die(${error2.$lang} . "$day.$month.$year");

       // include daily predictions
       include 'solarertrag_day_predictions.php';

       // Connect to mysql database
       @mysql_connect('localhost', 'user', 'password') or die(mysql_error());
       @mysql_select_db('solarmax') or die(mysql_error());

       // Check which view to use and define start and end limits
       switch ($period) {
          case 'day':
             $start['day'] = $day;
             $start['month'] = $month;
             $start['year'] = $year;
             $end = $start;
             break;
          case 'month':
             $start['day'] = 1;
             $start['month'] = $month;
             $start['year'] = $year;
             $end['day'] = 31;
             $end['month'] = $month;
             $end['year'] = $year;
             break;
          case 'year':
             $start['day'] = 1;
             $start['month'] = 1;
             $start['year'] = $year;
             $end['day'] = 31;
             $end['month'] = 12;
             $end['year'] = $year;
             break;
       }

       // Make sure we define a valid end date
       while (!checkdate($end['month'], $end['day'], $end['year']))
          $end['day']--;

       // Set predictions for chosen date
       $pred_day = ${'d_'.date('m', mktime(0, 0, 0, $start['month'], $start['day'], $start['year']))};

       // Include time in start and end delimiters
       $start = date('Y-m-d H:i:s', mktime(0, 0, 0, $start['month'], $start['day'], $start['year']));
       $end = date('Y-m-d H:i:s', mktime(23, 59, 59, $end['month'], $end['day'], $end['year']));

       // Remove old image files
       foreach (glob("img/*.png") as $image_name)
          unlink($image_name);

       // Create a filename with appended date to fool browser caches
       $image_name = 'img/data_' . date('YmdHis') . '.png';
       //   $image_name = 'data.png';

       // Check the desired view again and include and call the proper function
       switch ($period) {
          case 'day':
             include 'drawday.php';
             $text = draw_day($start, $end, $pred_day, $image_name, $table, $fontfile);
             break;
          case 'month':
             include 'drawmonth.php';
             $text = draw_month($start, $end, $pred_day, $image_name, $table, $fontfile);
             break;
          case 'year':
             include 'drawyear.php';
             $text = draw_year($start, $end, $pred_month, $image_name, $table, $fontfile);
             break;
       }
    ?>

    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
           "http://www.w3.org/TR/html4/strict.dtd">
    <html>
       <head>
          <title>Solarmax Watcher</title>
          <meta name="generator" content="Bluefish 1.0.7">
          <meta name="copyright" content="Frank Lassowski">
          <meta name="date" content="2010-09-20T12:50:38+0200">
          <meta http-equiv="content-type" content="text/html; charset=UTF-8">
          <meta http-equiv="expires" content="0">
          <link rel="stylesheet" type="text/css" href="solarertrag.css">
          <link rel="shortcut icon" href="img/sun.ico" type="image/x-icon">
       </head>
       <body>
       <div id="wrap">
       <?php include 'sitehead.php'; // if you don't want to have a title image delete this line ?>
          <form name="visualizer" method="post" action="<?php echo "solarertrag.php?wr=" . $wr ?>">
             <table cellspacing="6">
             	<tr align="center">
             	   <th colspan="3"><?php echo ${text11.$lang}; ?></th>
             	   <td></td>
             	   <td><?php echo ${text12.$lang}; ?>:</td>
             	   <td><?php echo ${text13.$lang}; ?>:</td>
             	   <td><?php echo ${text14.$lang}; ?>:</td>
             	</tr>
                <tr align="center">
                   <td><input type="radio" name="period" value="day" onclick="refreshDiagram()" <?php if ($period == 'day') echo "checked=\"checked\""; ?>> <?php echo ${text12.$lang}; ?></td>
                   <td><input type="radio" name="period" value="month" onclick="refreshDiagram()" <?php if ($period == 'month') echo "checked=\"checked\""; ?>> <?php echo ${text13.$lang}; ?></td>
                   <td><input type="radio" name="period" value="year" onclick="refreshDiagram()" <?php if ($period == 'year') echo "checked=\"checked\""; ?>> <?php echo ${text14.$lang}; ?></td>
                   <td><input type="submit" name="period" value="<?php echo ${text15.$lang}; ?>" onclick="setActualDate()"></td>
                   <td>
                      <input type="button" style="width:22px" onclick="decreaseDay()" value="<">
                      <input name="day" type="text" size="2" maxlength="2" value="<?php echo $day; ?>">
                      <input type="button" style="width:22px" onclick="increaseDay()" value=">">
                   </td>
                   <td>
                      <input type="button" style="width:22px" onclick="decreaseMonth()" value="<">
                      <input name="month" type="text" size="2" maxlength="2" value="<?php echo $month; ?>">
                      <input type="button" style="width:22px" onclick="increaseMonth()" value=">">
                   </td>
                   <td>
                      <input type="button" style="width:22px" onclick="decreaseYear()" value="<">
                      <input name="year" type="text" size="4" maxlength="4" value="<?php echo $year; ?>">
                      <input type="button" style="width:22px" onclick="increaseYear()" value=">">
                   </td>
                   <td>
                      <input type="submit" value="Go">
                   </td>
                </tr>
             </table>
          </form>
          <table cellspacing="6">
             <tr>
          <?php
          $result = @mysql_query("SELECT pac, kdy, kmt, kyr, kt0, tkk, sys FROM $table ORDER BY created DESC LIMIT 1") or die(mysql_error());
          echo '<td width="30%">', ${text1.$lang}, '</td><td class="right2"><b>', mysql_result( $result, 0, 0), '</b> Watt</td>';
          echo '<td class="left">', ${text2.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 1) / 10, '</b> kWh</td></tr>';          
          echo '<tr><td width="30%">', ${text3.$lang}, '</td><td class="right2"><b>', mysql_result( $result, 0, 5), '</b> °C</td>';
          echo '<td class="left">', ${text4.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 2), '</b> kWh</td></tr>';
          echo '<tr><td width="30%">', ${text5.$lang}, '</td><td class="right2"><b>', round( mysql_result( $result, 0, 3) * 0.683 / 1000, 3), '</b> to</td>';
          echo '<td class="left">', ${text6.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 3), '</b> kWh</td></tr>';
          echo '<tr><td width="30%">', ${text7.$lang}, '</td><td class="right2"><b>', round( mysql_result( $result, 0, 4) * 0.683 / 1000, 3), '</b> to</td>';
          echo '<td class="left">', ${text8.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 4), '</b> kWh</td></tr>';
          echo '<tr><td width="30%"><b>', ${text9.$lang}, '</b></td><td class="right2"><b>', ${'_'.mysql_result( $result, 0, 6).$lang}, '</b></td>';
          echo '<td class="left">', ${text10.$lang}, '</td><td align="right"><b>', round( mysql_result( $result, 0, 4) * 0.3405, 2), '</b> EUR</td>';
          echo '</tr></table>';
          echo $text;
          ?>
          <img src="<?php echo $image_name; ?>" name="Sonneneinstrahlungsdiagramm" alt="Sonneneinstrahlungsdiagramm">

          <script type="text/javascript"><!--
             function refreshDiagram() {
                document.forms.visualizer.submit();
             }

             window.setTimeout("refreshDiagram()", 60000);

             function checkDate(day, month, year) {
                var daysOfMonth = new Array(31, 30, 31, 28, 31, 30, 31, 31, 30, 31, 30, 31);
                if ((year % 4 == 0) && (month == 4))
                   return day > 0 && day < 30 && month > 0 && month < 13 && year > 0 && year < 32000;
                else
                   return year > 0 && year < 10000 && month > 0 && month < 13 && day > 0 && day <= daysOfMonth[month - 1];
             }

             function decreaseDay() {
                var day = parseInt(document.forms.visualizer.day.value, 10) - 1;
                if (day < 1) day = 31;
                var month = parseInt(document.forms.visualizer.month.value, 10);
                var year = parseInt(document.forms.visualizer.year.value, 10);
                while (!checkDate(day, month, year))
                   day--;
                document.forms.visualizer.day.value = day;
                document.forms.visualizer.submit();
             }

             function increaseDay() {
                var day = parseInt(document.forms.visualizer.day.value, 10) + 1;
                if (day > 31) day = 1;
                var month = parseInt(document.forms.visualizer.month.value, 10);
                var year = parseInt(document.forms.visualizer.year.value, 10);
                while (!checkDate(day, month, year))
                   day = (day % 31) + 1;
                document.forms.visualizer.day.value = day;
                document.forms.visualizer.submit();
             }

             function decreaseMonth() {
                var day = parseInt(document.forms.visualizer.day.value, 10);
                var month = parseInt(document.forms.visualizer.month.value, 10) - 1;
                if (month < 1) month = 12;
                var year = parseInt(document.forms.visualizer.year.value, 10);
                document.forms.visualizer.month.value = month;
                document.forms.visualizer.submit();
             }

             function increaseMonth() {
                var day = parseInt(document.forms.visualizer.day.value, 10);
                var month = parseInt(document.forms.visualizer.month.value, 10) + 1;
                if (month > 12) month = 1;
                var year = parseInt(document.forms.visualizer.year.value, 10);
                document.forms.visualizer.month.value = month;
                document.forms.visualizer.submit();
             }

             function decreaseYear() {
                var day = parseInt(document.forms.visualizer.day.value, 10);
                var month = parseInt(document.forms.visualizer.month.value, 10);
                var year = parseInt(document.forms.visualizer.year.value, 10) - 1;
                if (year < 1) year = 9999;
                document.forms.visualizer.year.value = year;
                document.forms.visualizer.submit();
             }

             function increaseYear() {
                var day = parseInt(document.forms.visualizer.day.value, 10);
                var month = parseInt(document.forms.visualizer.month.value, 10);
                var year = parseInt(document.forms.visualizer.year.value, 10) + 1;
                if (year > 9999) year = 1;
                document.forms.visualizer.year.value = year;
                document.forms.visualizer.submit();
             }

             function setActualDate() {
                var now = new Date();
                var day = now.getDate();
                var month = now.getMonth();
                var year = now.getFullYear();
                document.forms.visualizer.day.value = day;
                document.forms.visualizer.month.value = month + 1;
                document.forms.visualizer.year.value = year;
                document.forms.visualizer.submit();
             }
          --></script>
       </div>
       <div id="footer">
       <p>Copyright &copy; 2010 by <a href="mailto:info.lassowski.dyndns.org@arcor.de?subject=SolarMax Watcher">Frank Lassowski</a> and <a href="mailto:zagibu@gmx.ch?subject=SolarMax Watcher">zagibu</a> &nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp; <a href="http://lassowski.dyndns.org/BierderLOG/?p=impressum">Impressum</a> &nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp; Design by <a href="mailto:info.lassowski.dyndns.org@arcor.de?subject=SolarMax Watcher">Frank Lassowski</a></p>
       </div>
       </body>
    </html>