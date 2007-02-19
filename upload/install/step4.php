<?php
/*
	STEP4: 
		Initialize the game specific database schema

*/
if (!defined("VALID_PAGE")) die("Go Away!");

include("../config.php");
require_once("../includes/class_DB.php");

$validvars = array('overwrite','back','step5','doinit','gametype','modtype');
globalize($validvars);

if ($back) {
	gotopage("$PHP_SELF?step=3");
}
if ($step5) {
	gotopage("$PHP_SELF?step=5");
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

$db = DB::create(array('fatal' => 0, 'delaydb' => 1) + $dbconf);

$dberr = '';
$dbexists = $db->dbexists($dbname);
$dbdone = 0;
$tables = array();

// first see if the database already exists
if (!$dbexists) {
	gotopage("$PHP_SELF?step=3");
}
$db->selectdb($dbname);
$db->query("SHOW TABLES");
while ($r = $db->fetch_row(0)) {
	$tables[ $r[0] ] = 1;
} 

// find all gametype's in the directory
$gametypes = array();
$modtypes = array();
$dir = $dbtype;
if ($dh = opendir($dir)) {
	while (($file = readdir($dh)) !== false) {
		if ($file{0} == '.' or !is_dir("$dir/$file")) continue;
		$gametypes[] = $file;
	}
	closedir($dh);
}
if (!$gametype or !in_array($gametype, $gametypes)) $gametype = $gametypes[0];

// find all modtypes of the current gametype
$gametype = basename($gametype);	// do not allow things like '../../../etc/'
$modtype = basename($modtype);
$dir = "$dbtype/$gametype";
if ($dh = opendir($dir)) {
	while (($file = readdir($dh)) !== false) {
		if ($file{0} == '.' or !is_file("$dir/$file")) continue;
		if (substr($file, -4) != '.sql') continue;
		$modtypes[] = substr($file, 0, -4);
	}
	closedir($dh);
}
if (!$modtype or !in_array($modtype, $modtypes)) $modtype = $modtypes[0];

// set our gametype:modtype in the config
$db->query("UPDATE ${dbtblprefix}config SET value='" . $db->escape($gametype) . "' WHERE conftype='main' AND var='gametype' AND section=''");
$db->query("UPDATE ${dbtblprefix}config SET value='" . $db->escape($modtype) . "' WHERE conftype='main' AND var='modtype' AND section=''");

// load our SQL schema 
$schema = array();
if ($modtype) {
	$schema = preg_split('/;\s*/', 
		implode('', 
			array_map('rtrim', 
				preg_grep("/^(--|DROP|(UN)?LOCK|\\/\\*)/", file("$dbtype/$gametype/$modtype.sql"), PREG_GREP_INVERT)
			)
		)
	);
}
if (!$schema) err("Unable to read '$gametype:$modtype' database schema for installation!");

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
defaultTextStr = '';
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<input name="dosubmit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>STEP 4: Initializing Game Support</h3>
		<p>Select the game type (and mod) of the game server you want to parse logs for.
		</p>
	</div>

<?php if (!$dbexists): ?>
	<div class="row row-err">
		Database '<?php echo $dbname?>' does not exist yet. 
		Please go back to the previous installation step before proceeding.
		<br><a href="<?php echo $PHP_SELF?>?step=<?php echo $step-1?>">Click here to go back to previous step</a>
	</div>
<?php else: ?>
	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: left">
			<input id="tbloverwrite" name="overwrite" value="1" type="checkbox" <?php echo ($overwrite) ? 'checked' : ''; ?> />
			<label id="tbloverwritelable" for="tbloverwrite">Overwrite existing tables?<br>(warning: existing data in tables will be lost)</label>
			<br><br>
			<center>
				<div style="float: left">
				<div class="label">Select Gametype:</div>
				<select name="gametype" class="field" onchange="this.form.submit()">
					<?php foreach ($gametypes as $g): ?>
					<option<?php echo ($g == $gametype) ? ' selected' : ''; ?>><?php echo htmlentities($g)?></option>
					<?php endforeach; ?>
				</select>
				<noscript><input type="submit" value="Refresh" class="btn" /></noscript>
				</div>

				<div style="float: right">
				<div class="label">Select Modtype:</div>
				<select name="modtype" class="field">
					<?php foreach ($modtypes as $m): ?>
					<option<?php echo ($m == $modtype) ? ' selected' : ''; ?>><?php echo htmlentities($m)?></option>
					<?php endforeach; ?>
				</select>
				</div>
				<div class="row-spacer"></div>
			</center>
	<?php if (!$doinit): ?>
			<br><br>
			<b>When ready click the "Initialize Database" button below!</b>
	<?php endif; ?>
		</div>
		</div>
	</div>
<?php endif; ?>

<?php if ($dbexists and $doinit): ?>
	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: left">

<?php 

$errors = 0;
$table = '';

// initialize the database schema
$format = "<div class='row'><div style='float: left'><b>%s table '%s'</div><div style='float: right'>[ <span style='color: %s'><acronym title='%s'>%s</acronym></span> ]</b></div><div style='clear:both'>%s</div></div>\n";
$wascreated = array();
$queries = 0;
foreach ($schema as $sql) {
	if (!$sql) continue;		// ignore blank lines
	$queries++;

	// ignore table if the format of the sql is unknown
	if (!preg_match('/(?:CREATE TABLE|INSERT INTO) ([^\w])([\w\d_]+)\1/i', $sql, $m)) continue;
	$table = $m[2];
	$m[1] = strtoupper($m[1]);

	if ($dbtblprefix != 'ps_') {	// fix the table names to use the proper prefix
		$table = preg_replace('/^ps_/', $dbtblprefix, $table);
		$sql = str_replace($m[2], $table, $sql);
	}

#	// only drop the table if it existed when we initialized. To avoid confusion with 'unknown table' errors
	if ($overwrite and array_key_exists($table, $tables)) {
		$ok = $db->droptable($table);
		$errors += !$ok ? 1 : 0;
		$e = htmlentities(strip_tags($db->errstr), ENT_QUOTES);
		printf($format, "Dropping", $table, $ok ? "green" : "red", $e, $ok ? "OK" : "ERROR", $e);
	} elseif (!$overwrite and array_key_exists($table, $tables)) {
		$e = "Table exists; Not recreating it";
		printf($format, "Skipping", $table, "orange", $e, "OK", $e);
		continue;
	}

	$ok = $db->query($sql);
	$errors += !$ok ? 1 : 0;
	$wascreated[$table] = $ok;
	$e = htmlentities(strip_tags($db->errstr), ENT_QUOTES);
	printf($format, $m[0]{0} == "C" ? "Creating" : "Initializing", $table, $ok ? "green" : "red", $e, $ok ? "OK" : "ERROR", $e);
}
// schema had nothing in it
if (!$queries) {
	print "<div class='row'><div style='float: left'><b>No tables to create</div><div style='float: right'>[ <span style='color: green'><acronym title='OK'>OK</acronym></span> ]</b></div></div>\n";
}

$dbdone = 1;

?>

		</div>
		</div>
	</div>
<?php endif; ?>

<?php if ($errors): ?>
	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: left">
			Some errors were enountered while initializing the database. Some 'Drop' errors can be ignored if the
			tables were still created. If you think the database was initialized properly, please click the 
			'Continue' button below. If you encounter any problems with PsychoStats you may have to reinstall later.
		</div>
		</div>
	</div>
<?php elseif ($dbdone): ?>
	<div class="row row-err-success">
		<div align="center">
		<div style="width: 375px; text-align: left">
			<b>Database Initialized!!!</b><br>
			<b>Note:</b> Your configuration has been updated to use <b><?php echo "$gametype:$modtype" ?></b> for 
			processing logs.<br>
			Please click the 'Continue' button below to proceed!
		</div>
		</div>
	</div>
<?php endif; ?>

	<div class="row-spacer"></div>
	<div class="row">
		<div class="form-actions">
			<div style="float: right">
				<input name="doinit" value="Initialize Database" type="submit" class="btn" />
<?php if ($doinit and $dbdone): ?>
				<input name="step5" value="Continue &gt; &gt;" type="submit" class="btn" />
<?php endif; ?>
			</div>
			<div style="float: left">
				<input name="back" value="&lt; &lt; Back" type="submit" class="btn" />
			</div>
		</div>
	</div>
	<?php include('block_form_footer.html'); ?>
</form>
<div class="spacer"></div>
</div>
