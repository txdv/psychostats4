<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en_US">
<head>
<title>Smarty Template Error</title>
<style type="text/css">

body {
	background-color:	#fff;
	margin:			40px;
	font-family:		Lucida Grande, Verdana, Sans-serif;
	font-size:		12px;
	color:			#000;
}

#content  {
	border:			#999 1px solid;
	background-color:	#fff;
	padding:		20px 20px 12px 20px;
}

h1 {
	font-weight:		normal;
	font-size:		14px;
	color:			#990000;
	margin: 		0 0 4px 0;
}
</style>
</head>
<body>
	<div id="content">
		<h1>Smarty Template Error</h1>
		<?php echo htmlentities($error_str); ?>
	</div>
</body>
</html>