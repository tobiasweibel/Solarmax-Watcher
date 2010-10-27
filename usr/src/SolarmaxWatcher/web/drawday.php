<?php
    /*
       Simple solarmax visualizer php program written by zagibu@gmx.ch in July 2010
       This program was originally licensed under WTFPL 2 http://sam.zoy.org/wtfpl/
       Improvements by Frank Lassowski flassowski@gmx.de in August 2010
       This program is now licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html
    */

       function draw_day($start, $end, $pred_day, $image_name, $table, $fontfile) {
          // Select data from given day
          // If you are experiencing problems with the value 'kdy' especially the days very first 'kdy' is equal to the last one of the previous day comment in the next line and comment out the following one. Be aware: This is !!!untested!!! Don't slap me if something's going wrong!
          //$result = @mysql_query("SELECT HOUR(created) AS hour, MINUTE(created) AS minute, pac, kdy FROM $table WHERE created BETWEEN '$start' AND '$end' LIMIT 1, 999999999") or die(mysql_error());
          $result1 = @mysql_query("SELECT HOUR(created) AS hour, MINUTE(created) AS minute, pac FROM $table WHERE created BETWEEN '$start' AND '$end'") or die(mysql_error());
          $result2 = @mysql_query("SELECT HOUR(created) AS hour, MINUTE(created) AS minute, kdy, tkk, udc FROM $table WHERE created BETWEEN '$start' AND '$end'") or die(mysql_error());
          
          if (mysql_num_rows($result1) == 0)
          {
             // No data...create dummy image
             $image = imagecreatetruecolor(10, 10);
             $white = imagecolorallocate($image, 255, 255, 255);
             imagefill($image, 0, 0, $white);
             imagepng($image, $image_name);
             imagedestroy($image);
          
             return $GLOBALS["error3".$GLOBALS[lang]] . '<br />';
          }

          // Initialize pac image
          // Sun rise and fall during longest day at your place, must be integer
          $rise = 5; $fall = 22;
          // Pixel per hour should be equal to log entries per hour (I log every minute)
          $px_per_hour = 39;
          // Width = hours * px per hour + gap
          $width = ($fall - $rise) * $px_per_hour +157;
          $maxpac = 5200;
          $maxkdy = 40;
          $maxtkk = 60;
          $lasttkk = 0;
          $maxudc = 400;
          $lastudc = 0;
          // How many W per diagram line
          $step_w = 500;
          $step_kdy = 5;
          $step_tkk = 8;
          $step_udc = 40;
          // How many px per diagram line (W/px = $step_w / $vert_px)
          $vert_px = 30;
          // Height = number of lines * px per line + px per line (for 0-line) + gap
          $gap = 50;
          $height = $maxpac / $step_w * $vert_px + $gap + 10;
          // Create image, prepare colors and set background to white
          $image = imagecreatetruecolor($width, $height);
          // get the colors
          include 'colors.php';
          imagefill($image, 0, 0, $white);

          // Draw horizontal lines with some space above and below
          for ($i = 0; $i <= $maxpac / $step_w; $i++) {
             // Create horizontal grid line
             $ypos = $height - $i * $vert_px - $gap;
             imageline($image, 12, $ypos, $width - 110, $ypos, $gray);
             // Draw the pac value at the end of the horizontal line
             $pac = $i * $step_w;
             $kdy = $i * $step_kdy;
             $tkk = $i * $step_tkk;
             $udc = $i * $step_udc;
             imagefttext($image, 7, 0, $width - 109, $ypos + 4, $black, $fontfile, $pac);
             imagefttext($image, 7, 0, $width - 74, $ypos + 4, $blue, $fontfile, $kdy);
             imagefttext($image, 7, 0, $width - 47, $ypos + 4, $black, $fontfile, $tkk);
             imagefttext($image, 7, 0, $width - 25, $ypos + 4, $red, $fontfile, $udc);             
          }

          // Draw vertical lines with some space at the left and right
          for ($i = 0; $i <= $fall - $rise; $i++) {
             // Create vertical grid line
             $xpos = $i * $px_per_hour + 25;
             imageline($image, $xpos, 5, $xpos, $height - $gap + 6, $gray);
             // Draw the hour value at the end of the vertical line
             $hour = ($i + $rise) % 24 . ':00';
             imagefttext($image, 8, 0, $xpos - 10, $height - $gap + 18, $black, $fontfile, $hour);
          }

          // Draw pac values
          while($row = mysql_fetch_assoc($result1)) {
             // Determine x position
             $xpos = floor (($row['hour'] - $rise) * $px_per_hour + $row['minute'] / 60 * $px_per_hour + 25);
             if ($xpos > $xpos_old) {
                // Calculate y position with logged pac
                $pac = $row['pac'] / $step_w * $vert_px;
                // Draw pac line
                imageline($image, $xpos, $height - $gap, $xpos, $height - $gap - $pac, $green);
             }
             $xpos_old = $xpos;
          }

          imagefttext($image, 7, 0, $width - 107, 10, $black, $fontfile, "(W)");
          imagefttext($image, 6, 0, $width - 81, 10, $blue, $fontfile, "(kWh)");
          imagefttext($image, 6, 0, $width - 50, 10, $black, $fontfile, "(°C)");
          imagefttext($image, 7, 0, $width - 25, 10, $red, $fontfile, "(V)");

          // Draw prediction line
          $pred = $pred_day / $step_kdy * $vert_px;
          imageline($image, 12, $height - $pred - $gap, $width - 122, $height - $pred - $gap, $blue);

          // Draw other logged values: kdy, tkk, udc
          while($row = mysql_fetch_assoc($result2)) {
             // Determine x position
             $xpos = ($row['hour'] - $rise) * $px_per_hour + $row['minute'] / 60 * $px_per_hour + 25;
             $lastxpos = $xpos;
             // Logged kdy is ten times as high as effective
             $kdy = $row['kdy'] / 10 / $step_kdy * $vert_px;
             // Draw kdy dot
             imagesetpixel($image, $xpos, $height - $gap - $kdy, $blue);
             // draw tkk as a stair-line
             $tkk = $row['tkk'] / $step_tkk * $vert_px;
                if ($lasttkk == 0) {
                imagesetpixel($image, $xpos, $height - $gap - $tkk, $black);
                } else {
                imageline($image, $lastxpos, $height - $gap - $lasttkk, $xpos, $height - $gap - $tkk, $black);
                }
             $lasttkk = $tkk;
             // Logged udc is ten times as high as effective
             $udc = $row['udc'] / 10 / $step_udc * $vert_px;
                if ($lastudc == 0) {
                imagesetpixel($image, $xpos, $height - $gap - $udc, $red);
                } else {
                imageline($image, $lastxpos, $height - $gap - $lastudc, $xpos, $height - $gap - $udc, $red);
                }
             $lastudc = $udc;
          }

          //explain colored lines
          imageline($image, $width - ($width * 4 / 4) + 10, $height - 15, $width - ($width * 4 / 4) + 25, $height - 15, $blue);
          imageline($image, $width - ($width * 2 / 4) + 0, $height - 15, $width - ($width * 2 / 4) + 15, $height - 15, $black);
          imageline($image, $width - ($width * 1 / 4) - 30, $height - 15, $width - ($width * 1 / 4) - 15, $height - 15, $red);
          imagefttext($image, 7, 0, $width - ($width * 4 / 4) + 40, $height - 12, $black, $fontfile, $GLOBALS["graphday2".$GLOBALS[lang]]);
          imagefttext($image, 7, 0, $width - ($width * 2 / 4) + 30, $height - 12, $black, $fontfile, $GLOBALS["graphday3".$GLOBALS[lang]]);
          imagefttext($image, 7, 0, $width - ($width * 1 / 4) + 0, $height - 12, $black, $fontfile, $GLOBALS["graphday4".$GLOBALS[lang]]);
          
          imagepng($image, $image_name);
          imagedestroy($image);

          //return '<p>Leistung in Watt im Verlauf des Tages, Tagesertrag, Temperatur WR, DC-Spannung</p>';
          return '<p>' . $GLOBALS["graphday1".$GLOBALS[lang]] . '</p>';
       }
?>