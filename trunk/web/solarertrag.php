<?php
	/*
	Simple solarmax visualizer php program written by zagibu <zagibu@gmx.ch> in July 2010
	This program was originally licensed under WTFPL 2 http://sam.zoy.org/wtfpl/
	Improvements by Frank Lassowski <flassowski@gmx.de> in August 2010
	Further improvements by sleepprogger <wwrStuff@gmx.de> in January 2012

	This program is now licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html

	To run this program your server must have PHP and the gd extension enabled.
	Put this and all the other files contained in 'solarertrag.tar.gz'
	in your web directory, for example /var/www/ and call it with
	http://yourwebadress/solarertrag.php
	*/

	//check cookie and $_POST['show']

	$switch_array = array("yield", "accu", "predday", "volt", "temp", "gridday", "numbersmonth", "predmonth", "avg", "gridmonth", "numbersyear", "percent", "gridyear");

	if (empty($_POST['show']) and (!isset($_COOKIE['values']))) {
		$show = $switch_array;
		setcookie("values",implode(' ', $switch_array), time()+7*24*3600);
	}
	elseif (!empty($_POST['show']) and (!isset($_COOKIE['values']))){
		$show = $_POST['show'];
		setcookie("values",implode(' ', $show), time()+7*24*3600);
	}
	elseif (empty($_POST['show']) and isset($_COOKIE['values'])) {
		$show = array($_COOKIE['values']);
	}
	else {
		$show = $_POST['show'];
		setcookie("values",implode(' ', $show), time()+7*24*3600);
	}

	$show_text = implode(' ', $show);

	// select table by page query ?wr=
	// to hopefully avoid SQL injections we only accept numbers :-)

	if (empty($_GET['wr'])) {
		$wr = 1;
	}
	elseif (preg_match('/[^0-9]/', $_GET['wr'])) {
		$wr = 1;
	}
	else {
		$wr = $_GET['wr'];
	}
	$table="log{$wr}";

	// which language does the users browser prefer
	$lang=substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if ($lang <> "de" && $lang <> "en" && $lang <> "nl" && $lang <> "fr" && $lang <> "es" && $lang <> "it")
		$lang="en";

	$sublang=substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 3, 2);
	if ($sublang <> "de" && $sublang <> "en" && $sublang <> "nl" && $sublang <> "fr" && $sublang <> "es" && $sublang <> "it" && $sublang <> "us" && $sublang <> "ru" && $sublang <> "ch")
		$sublang = "us";

	// include language file
	include 'lang.php';

	// if we want to switch to seperate language files we have to use seperate language files and the following line instead
	// include 'lang_' . $lang . '.php';

	// Which font to use in the graphs
	// for Windows based servers look at C:/Windows/Fonts for appropriate fonts
	$fontfile="/usr/share/fonts/truetype/ttf-dejavu/DejaVuSansMono.ttf";

	// Check other POST vars
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
	@mysql_connect('localhost', 'solaruser', 'userpassword') or die(mysql_error());
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

	// Check the desired view again and include and call the proper function
	$input0 = "<input style=\"DISPLAY:none\" type=\"checkbox\" name=\"show[]\" value=\"";
	$input1 = "<input type=\"checkbox\" name=\"show[]\" value=\"";
	$input2 = "\" onclick=\"refreshDiagram()\" ";
	$input3 = "checked=\"checked\" >";
	$input4 = "\n<div style=\"font-size:0.7em\">\n<p>";
	switch ($period) {
		case 'day':
		include 'drawday.php';
		$text = draw_day($start, $end, $pred_day, $image_name, $table, $fontfile, $show_text).$input4;
		for ($i = 0; $i <= 5; $i++) {
			$text = $text.$input1.$switch_array[$i].$input2;
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3.${switch_array.$lang}[$i];
			else $text = $text.">".${switch_array.$lang}[$i];
		}
		for ($i = 6; $i <= 12; $i++) {
			$text = $text.$input0.$switch_array[$i]."\" ";
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3."\n";
			else $text = $text.">\n";
		}
		break;

		case 'month':
		include 'drawmonth.php';
		$text = draw_month($start, $end, $pred_day, $image_name, $table, $fontfile, $show_text).$input4;
		for ($i = 6; $i <= 9; $i++) {
			$text = $text.$input1.$switch_array[$i].$input2;
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3.${switch_array.$lang}[$i];
			else $text = $text.">".${switch_array.$lang}[$i];
		}
		for ($i = 0; $i <= 5; $i++) {
			$text = $text.$input0.$switch_array[$i]."\" ";
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3."\n";
			else $text = $text.">\n";
		}
		for ($i = 10; $i <= 12; $i++) {
			$text = $text.$input0.$switch_array[$i]."\" ";
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3."\n";
			else $text = $text.">\n";
		}
		break;

		case 'year':
		include 'drawyear.php';
		$text = draw_year($start, $end, $pred_month, $image_name, $table, $fontfile, $show_text).$input4;
		for ($i = 10; $i <= 12; $i++) {
			$text = $text.$input1.$switch_array[$i].$input2;
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3.${switch_array.$lang}[$i];
			else $text = $text.">".${switch_array.$lang}[$i];
		}
		for ($i = 0; $i <= 9; $i++) {
			$text = $text.$input0.$switch_array[$i]."\" ";
			if (preg_match("/".$switch_array[$i]."/", $show_text)) $text = $text.$input3."\n";
			else $text = $text.">\n";
		}
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
		<meta name="date" content="2010-12-27T20:59:54+0100">
		<meta http-equiv="content-type" content="text/html; charset=UTF-8">
		<meta http-equiv="expires" content="0">
		<link rel="stylesheet" type="text/css" href="solarertrag.css">
		<link rel="shortcut icon" href="img/sun.ico" type="image/x-icon">
		<!-- TODO use proper script tag with version ;) -->
		<script>
			// Small ajaxRequest to check for new Version
			function getXMLHttpObject(){
				var xmlHttp;
				try{
				// Firefox, Opera 8.0+, Safari
				xmlHttp=new XMLHttpRequest();
				}catch (e){
				// Internet Explorer
				try{
				xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
				}catch (e){
				try{
				xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
				}catch (e){
				alert("Your browser does not support AJAX!");
				return false;
				}}}
				return xmlHttp;
			}
			function checkForUpdate(){
				var request = getXMLHttpObject();
				request.open("GET", "WebRequest.php?secret=WeDoNotWantBotsInHereWhichWouldResultInALargeAmountOfRequests", false);
				request.send(null);
				var footerTag = document.getElementById('footerp');
				footerTag.innerHTML == request.responseText;
			}
		</script>
	</head>
	<body onload="checkForUpdate()">
		<div id="wrap">
		<?php include 'sitehead.php'; // if you don't want to have a title image delete this line
		?>
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
							<input type="button" style="width:22px" onclick="changeDate(-1, 0, 0)" value="<">
							<input name="day" type="text" size="2" maxlength="2" value="<?php echo $day; ?>">
							<input type="button" style="width:22px" onclick="changeDate(1, 0, 0)" value=">">
						</td>
						<td>
							<input type="button" style="width:22px" onclick="changeDate(0, -1, 0)" value="<">
							<input name="month" type="text" size="2" maxlength="2" value="<?php echo $month; ?>">
							<input type="button" style="width:22px" onclick="changeDate(0, 1, 0)" value=">">
						</td>
						<td>
							<input type="button" style="width:22px" onclick="changeDate(0, 0, -1)" value="<">
							<input name="year" type="text" size="4" maxlength="4" value="<?php echo $year; ?>">
							<input type="button" style="width:22px" onclick="changeDate(0, 0, 1)" value=">">
						</td>
						<td>
							<input type="submit" value="Go">
						</td>
					</tr>
				</table>
					<table cellspacing="6">
						<tr>
							<?php
          /* currency echange rates
   Of course you can take static values for the exchange rates,
   but it's much nicer if these are up to date.
   For that it is needed that the bash script 'currency.sh' is
   run once or twice a day to keep the values in the file 'currency.txt'
   up to date.
   If you prefer static values comment out the following 

   $fd = fopen ("currency.txt", "r");
   while (!feof ($fd))
   {
      //$buffer = fgets($fd, 4096);
      $lines[] = fgets($fd, 4096);
   }
   fclose ($fd);
   
   $currde = 1;
   $currtxtde = "EUR";
   $curren = $lines[1];
   $currtxten = "£";
   $currnl = 1;
   $currtxtnl = "EUR";
   $currfr = 1;
   $currtxtfr = "EUR";
   $curres = 1;
   $currtxtes = "EUR";
   $currit = 1;
   $currtxtit = "EUR";
   $currch = $lines[3];
   $currtxtch = "CHF";
   $currus = $lines[0];
   $currtxtus = "US$";
   $currru = $lines[2];
   $currtxtru = "руб";
*/
								$result = @mysql_query("SELECT pac, kdy, kmt, kyr, kt0, tkk, sys FROM $table ORDER BY created DESC LIMIT 1") or die(mysql_error());
								echo '<td width="30%">', ${text1.$lang}, '</td><td class="right2"><b>', mysql_result( $result, 0, 0), '</b> Watt</td>'."\n";
								echo '<td class="left">', ${text2.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 1) / 10, '</b> kWh</td></tr>'."\n";          
								echo '<tr><td width="30%">', ${text3.$lang}, '</td><td class="right2"><b>', mysql_result( $result, 0, 5), '</b> °C</td>'."\n";
								echo '<td class="left">', ${text4.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 2), '</b> kWh</td></tr>'."\n";
								echo '<tr><td width="30%">', ${text5.$lang}, '</td><td class="right2"><b>', round( mysql_result( $result, 0, 3) * 0.683 / 1000, 3), '</b> to</td>'."\n";
								echo '<td class="left">', ${text6.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 3), '</b> kWh</td></tr>'."\n";
								echo '<tr><td width="30%">', ${text7.$lang}, '</td><td class="right2"><b>', round( mysql_result( $result, 0, 4) * 0.683 / 1000, 3), '</b> to</td>'."\n";
								echo '<td class="left">', ${text8.$lang}, '</td><td align="right"><b>', mysql_result( $result, 0, 4), '</b> kWh</td></tr>'."\n";
								echo '<tr><td width="30%"><b>', ${text9.$lang}, '</b></td><td class="right2"><b>', ${'_'.mysql_result( $result, 0, 6).$lang}, '</b></td>'."\n";
//								echo '<td class="left">', ${text10.$lang}, '</td><td align="right"><b>', round( mysql_result( $result, 0, 4) * 0.3405 * ${curr.$sublang}, 2), '</b> ', ${currtxt.$sublang}, '</td>'."\n";
								echo '<td class="left">', ${text10.$lang}, '</td><td align="right"><b>', round( mysql_result( $result, 0, 4) * 0.3405, 2), '</b> EUR</td>'."\n";
								echo '</tr></table>'."\n";
								echo $text;
								echo "</form>\n";
							?>
							<img src="<?php echo $image_name; ?>" name="Sonneneinstrahlungsdiagramm" alt="Sonneneinstrahlungsdiagramm">
							<script type="text/javascript"><!--
								function refreshDiagram() {
									document.forms.visualizer.submit();
								}

								window.setTimeout("refreshDiagram()", 60000);

								function changeDate(day, month, year) {
                  // Get values out of input fields
									var dayField = parseInt(document.forms.visualizer.day.value, 10);
									var monthField = parseInt(document.forms.visualizer.month.value, 10);
									var yearField = parseInt(document.forms.visualizer.year.value, 10);

                  // Create a new Date object for date validation
									var date = new Date();

                  // Set date and update in one step
                  date.setFullYear(yearField + year);
                  date.setMonth(monthField + month - 1);
                  date.setDate(dayField + day);

                  // Update input fields
                  document.forms.visualizer.year.value = date.getFullYear();
                  document.forms.visualizer.month.value = date.getMonth() + 1;
                  document.forms.visualizer.day.value = date.getDate();

									document.forms.visualizer.submit();
								}

                function setActualDate() {
                  var date = new Date();

                  // Update input fields
                  document.forms.visualizer.year.value = date.getFullYear();
                  document.forms.visualizer.month.value = date.getMonth() + 1;
                  document.forms.visualizer.day.value = date.getDate();

									document.forms.visualizer.submit();
                }
							--></script>
						</div>
						<div id="footer">
							<p id="footer"><b id=footerp><a href="http://sourceforge.net/projects/solarmaxwatcher/files/latest/download?source=files">Updates ?</a></b><a href="https://sourceforge.net/projects/solarmaxwatcher/">Solarmax Watcher at Sourceforge</a>&nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp; <a href="http://URL/to/your/impressum">Impressum</a> &nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp; Design by <a href="mailto:info.lassowski.dyndns.org@arcor.de?subject=SolarMax Watcher">Frank Lassowski</a></p>
						</div>
					</body>
				</html>
