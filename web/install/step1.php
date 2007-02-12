<?php
/*
	STEP1:
		Get basic DB config and verify it works before going to step 2.
*/
if (!defined("VALID_PAGE")) die("Go Away!");

require_once("../includes/class_DB.php");

$validvars = array('dotest','step2');
globalize($validvars);

// form fields ...
$formfields = array(
	'dbtype'	=> array('label' => 'DB Type:',			'val' => 'B', 'statustext' => "Type of DB server you are using."),
	'dbhost'	=> array('label' => 'Hostname:',		'val' => 'B', 'statustext' => "Hostname of server (or localhost if it's on the same machine as this site)"),
	'dbport'	=> array('label' => 'Port:',			'val' => 'N', 'statustext' => "Server port (leave blank for default)"),
	'dbname'	=> array('label' => 'DB Name:',			'val' => 'B', 'statustext' => "Name of the DB to use for stats."),
	'dbuser'	=> array('label' => 'Username:',		'val' => '',  'statustext' => "Username to connect to server."),
	'dbpass'	=> array('label' => 'Password:',		'val' => '',  'statustext' => "Password to connect to server."),
	'dbtblprefix'	=> array('label' => 'Table Name Prefix:',	'val' => '',  'statustext' => "Prefix to prepend all table names with."),
);

$_REQUEST['dbtype'] = 'mysql';

$testresult = FALSE;
$testmsg = "";

$form = array();
$errors = array();

if ($submit) {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if ($step2) {
		$url = "$PHP_SELF?step=2";
		foreach (array_keys($formfields) as $key) {
			$url .="&$key=" . urlencode($form[$key]);
		}
		gotopage($url);
	}

	if (!count($errors)) {
		if ($dotest) {
			$version = '0.0';
			$db = DB::create(array('fatal' => 0, 'delaydb' => 1) + $form);
			$testresult = $db->connected;
			if ($testresult) {
				$info = $db->server_info();
				$version = $info['version'];
				if (version_compare($version,'4.1.11') == -1) {
					$testresult = FALSE;
					$testmsg = "Your version of MYSQL <b>v$version</b> is too low.<br>MYSQL <b>v4.1.11</b> or higher is required.";
				} else {
					$testmsg = strtoupper($form['dbtype']) . " connection established!<br>Version: $version<br>Click the 'Continue' button to proceed";
				}
			} else {
				$testmsg = $db->errstr;
			}
		} else {
			
		}
	}
} else {
	$form = array(
		'dbtype'	=> $dbtype,
		'dbhost'	=> $dbhost,
		'dbport'	=> $dbport,
		'dbname'	=> $dbname,
		'dbuser'	=> $dbuser,
		'dbpass'	=> $dbpass,
		'dbtblprefix'	=> $dbtblprefix,
	);
}

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<?php if ($testresult) savepost()?>
<input name="submit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>STEP 1: Database Configuration</h3>
		<p>
			Please enter the settings for your database server.<br>
			Click on "Test Connection" when ready to proceed.
		</p>
	</div>

<?php if ($dotest and $testmsg): ?>
	<center>
	<?php if ($testresult): ?>
		<div class="row row-err-success">
	<?php else: ?>
		<div class="row row-err">
	<?php endif; ?>
	<?php echo $testmsg ?>
	</div>
	</center>
	<div class="row-spacer"></div>
<?php endif; ?>

	<div class="row<?php echo $formfields['dbtype']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbtype']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbtype']['label'])?>
		</span>
		<span class="input">
			<select name="dbtype" class="field">
				<option value="mysql">MySQL v4.1.11+</option>
			</select>
			<?php if ($formfields['dbtype']['error']) print "<p class='err'>".htmlentities($formfields['dbtype']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['dbhost']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbhost']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbhost']['label'])?>
		</span>
		<span class="input">
			<input name="dbhost" value="<?php echo htmlentities($form['dbhost'])?>" type="text" size="30" class="field" />
			<?php if ($formfields['dbhost']['error']) print "<p class='err'>".htmlentities($formfields['dbhost']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['dbport']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbport']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbport']['label'])?>
		</span>
		<span class="input">
			<input name="dbport" value="<?php echo htmlentities($form['dbport'])?>" type="text" size="6" class="field" />
			<?php if ($formfields['dbport']['error']) print "<p class='err'>".htmlentities($formfields['dbport']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['dbname']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbname']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbname']['label'])?>
		</span>
		<span class="input">
			<input name="dbname" value="<?php echo htmlentities($form['dbname'])?>" type="text" size="30" class="field" />
			<?php if ($formfields['dbname']['error']) print "<p class='err'>".htmlentities($formfields['dbname']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['dbtblprefix']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbtblprefix']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbtblprefix']['label'])?>
		</span>
		<span class="input">
			<input name="dbtblprefix" value="<?php echo htmlentities($form['dbtblprefix'])?>" type="text" size="20" class="field" />
			<?php if ($formfields['dbtblprefix']['error']) print "<p class='err'>".htmlentities($formfields['dbtblprefix']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['dbuser']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbuser']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbuser']['label'])?>
		</span>
		<span class="input">
			<input name="dbuser" value="<?php echo htmlentities($form['dbuser'])?>" type="text" size="20" class="field" />
			<?php if ($formfields['dbuser']['error']) print "<p class='err'>".htmlentities($formfields['dbuser']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['dbpass']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['dbpass']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['dbpass']['label'])?>
		</span>
		<span class="input">
			<input name="dbpass" value="<?php echo htmlentities($form['dbpass'])?>" type="text" size="20" class="field" />
			<?php if ($formfields['dbpass']['error']) print "<p class='err'>".htmlentities($formfields['dbpass']['error'])."</p>"?>
		</span>
	</div>

	<div class="row-spacer"></div>
	<div class="row">
		<div class="form-actions">
			<div style="float: right">
				<input name="dotest" value="Test Connection" type="submit" class="btn" />
<?php if ($testresult): ?>
				<input name="step2" value="Continue &gt; &gt;" type="submit" class="btn" />
<?php endif; ?>
			</div>
		</div>
	</div>
	<?php include('block_form_footer.html'); ?>
</form>
<div class="spacer"></div>
</div>
