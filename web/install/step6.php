<?php
/*
	STEP6: 
		Verify the themes_compiled directory is writable

*/
if (!defined("VALID_PAGE")) die("Go Away!");

include("../config.php");
require_once("../includes/class_DB.php");

$validvars = array('test','step7');
globalize($validvars);

// form fields ...
$formfields = array(
	'compiledir'  => array('label' => 'Path:', 		'val' => '', 'statustext' => "Enter a valid path"),
);

if ($step7) {
	gotopage("$PHP_SELF?step=99");
}

$dbconf = array(
	'dbtype'	=> $dbtype,
	'dbhost'	=> $dbhost,
	'dbport'	=> $dbport,
	'dbname'	=> $dbname,
	'dbuser'	=> $dbuser,
	'dbpass'	=> $dbpass,
	'dbtblprefix'	=> $dbtblprefix
);

$db = DB::create(array('fatal' => 0) + $dbconf);

$form = array();
$errors = array();
$fatal = '';
$was_touched 	= false;
$was_created 	= false;
$is_writable 	= false;
$is_valid_path 	= false;

$path = tmppath('ps_themes_compiled');

if ($test) {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	if ($form['compiledir'] == '') $form['compiledir'] = tmppath('ps_themes_compiled');

	do_test($form['compiledir']);
	$path = $form['compiledir'];

	$errors = all_form_errors($formfields);

	if (!count($errors) and $is_valid_path) {
		$db->query("UPDATE ${dbtblprefix}config SET value='" . $db->escape($path) . "' WHERE conftype='theme' AND section='' AND var='compiledir'");
	}
} else {
	do_test($path);
	$form['compiledir'] = $path;
}

function do_test(&$path) {
	global $was_created, $is_writable, $was_touched, $is_valid_path;

	// if the path was absolute to the install directory, change it to be 1 level higher	
	if ($path == catfile(dirname($_SERVER["SCRIPT_FILENAME"]),'ps_themes_compiled')) {
		$path = dirname(dirname($path)) . "/ps_themes_compiled";
	}

	// if the path does not exist (And is not a file already) try to create it
//	$was_created = false;
	if (!is_dir($path) and !file_exists($path)) {
		$was_created = @mkdir($path, 0777);
	}

	$is_writable = is_writable($path);
	$was_touched = false;
	if ($is_writable) {
		$file = substr(md5(uniqid(rand())), rand(0,7), 8);
		$was_touched = touch("$path/$file");
		if ($was_touched) @unlink("$path/$file");
	}

	$is_valid_path = ($is_writable and $was_touched);
}

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
defaultTextStr = '';
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<input name="submit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>STEP 6: Verifying themes can compile</h3>
		<p>Current Path: <?php echo $path ?></p>
		<p>Permissions: <?php echo @fileperms($path) ? substr(sprintf('%o', @fileperms($path)), -4) : '000'?></p>
	</div>

	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: justify">
			PsychoStats uses compiled themes in order to increase page performance.
			This requires a directory that must be writable by the web server. By default PsychoStats
			will try to use the system temp directory but this does not always work for everyone. So, if 
			themes are failing you can change the path below.
			<br><br>
			It's best to enter full absolute paths in the input below. Relative paths may not work properly.
			<br><br>			
		</div>

	<div class="row<?php echo $formfields['compiledir']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['compiledir']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['compiledir']['label'])?>
		</span>
		<span class="input">
			<input name="compiledir" value="<?php echo htmlentities($form['compiledir'])?>" type="text" size="50" class="field" />
			<?php if ($formfields['compiledir']['error']) print "<p class='err'>".htmlentities($formfields['compiledir']['error'])."</p>"?>
		</span>
	</div>
	<div class="row-spacer"></div>

		<div style="width: 375px; text-align: justify">
<?php if ($is_writable and $was_touched): ?>
			Specified direcory <span style="color: green; font-weight: bold">IS writable</span>!<br>
			Themes should work perfectly fine.<br>
			You're all set and can click the 'Continue' button below to proceed.
<?php elseif ($is_writable and !$was_touched): ?>
			Specified direcory <span style="color: red; font-weight: bold">IS NOT writable</span>!<br>
			The directory looked like it was writable but when I tried to write a test file it failed.
			You can try another directory above. If all else fails try removing the 'ps_themes_compiled'
			from the path you enter.
<?php elseif (!$is_writable): ?>
			Specified direcory <span style="color: red; font-weight: bold">IS NOT writable</span>!<br>
			<b>Attempting to skip this step will result in your stats NOT working at all.</b>
	<?php if ($is_windows): ?>
			<br><br>If you're running the IIS webservices you need to ... 
			bend over and kiss your ass goodbye because I don't know what to do!
			<br><br>Obviously, the instructions here need to be updated to proper windows instructions.
			If you can offer any useful tips or guidance here please let Stormtrooper know. Thanks!
	<?php else: ?>
			<br><br>In linux environments this is usually very easy to fix. Simply login to this server and 
			issue the following command (make sure it's all on ONE line):
			<blockquote style="text-align: left"><i>chmod 777 <?php echo $path ?></i></blockquote>
			However, if the current path is the system directory then you might not be able to change the 
			permissions on the directory. You should try setting the path to a sub directory of your stats site
			(for example, try entering "themes_compiled" in the field above)
	<?php endif; ?>
			Then click the 'Test' button below to see if it works.
<?php endif; ?>
		</div>
		</div>
	</div>

	<div class="row-spacer"></div>
	<div class="row">
		<div class="form-actions">
			<div style="float: right">
				<input name="test" value="Test Directory!" type="submit" class="btn" />
<?php if ($is_writable): ?>
				<input name="step7" value="Continue &gt; &gt;" type="submit" class="btn" />
<?php endif; ?>
			</div>
		</div>
	</div>
	<?php include('block_form_footer.html'); ?>
</form>
<div class="spacer"></div>
</div>


