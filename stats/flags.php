<html>
<head>
<style type="text/css">
body {
	margin: 10px 30px 10px 30px;
}
#container { }
div {
	font-family: tohoma; 
	font-size: 10px;
}
.small-flag {
	float: left;
	padding: 5px 5px;
	text-align: center;
	width: 50px;
	border: 1px solid ;
}
.lrg-flag {
	position: absolute;
	padding: 0;
	border: 1px solid black;
	background-color: #333;
	color: white;
	text-align: center;
	font-weight: bold;
	font-size: 14px;
	display: block;
	visibility: hidden;
}
</style>
</head>
<body>
<script src="themes/default/webcore.js" type="text/javascript"></script>
<script type="text/javascript">
var web = new webcore();
web.startMouseCapture();
lastflag = -1;
function openflag(i) {
  if (!i) return;
  var flag = 'flag_'+i;
  var b = web.getObj(flag);
  if (!b) return;
  var divwidth = web.getObjWidth(b);
  var divheight = web.getObjHeight(b);
  var mx = web.client.getMouseX();
  var my = web.client.getMouseY();
//  web.setPos(flag, mx+8, my+16);
  web.setPos(flag, mx-(divwidth/2), my+4);
  web.hideObj(flag, 0);
  lastflag = i;
}

function closeflag() {
  if (lastflag < 0) return;
  var flag = 'flag_'+lastflag;
  web.hideObj(flag, 1);
  lastflag = -1;
}
</script>
<?php

$dir = "images/flags";
$files = array();

if ($dh = opendir($dir)) {
	while (($file = readdir($dh)) !== false) {
		if (@is_dir("$dir/$file")) continue;		// ignore directories
		if (substr($file,0,1) == '.') continue;		// ignore . files
		$basename = basename($file, ".png");
		if ($basename == $file) continue;		// not a png, ignore
		$files[] = $basename;
	}
	closedir($dh);
}
sort($files);
?>

<div id="container">
<?php

foreach ($files as $f) {
#	print "<div class='small-flag'><img src='$dir/$f.png' onmouseover='openflag(\"$f\")' onmouseout='closeflag()' alt='" . strtoupper($f) . "'/><br>" . strtoupper($f) . "</div>\n";
#	print "<div id='flag_$f' class='lrg-flag'><img src='$dir/$f.gif' alt='" . strtoupper($f) . "'/><br>" . strtoupper($f) . "</div>\n";
	print "<div class='small-flag'><img src='$dir/$f.png' alt='" . strtoupper($f) . "'/><br>" . strtoupper($f) . "</div>\n";
}

?>
</div>
</body>
</html>
