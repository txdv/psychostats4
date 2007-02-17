<?php
/*
	STEP3: 
		Initialize the database schema

*/
if (!defined("VALID_PAGE")) die("Go Away!");

include("../config.php");
require_once("../includes/class_DB.php");

$validvars = array('drop','overwrite','back','step4','doinit');
globalize($validvars);

if ($back) {
	gotopage("$PHP_SELF?step=1");
}
if ($step4) {
	gotopage("$PHP_SELF?step=4");
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
$dbcreated = 0;
$dbdropped = 0;
$tables = array();
$dbdone = 0;

// load table total for informational purposes
if ($dbexists) {
	$db->selectdb($dbname);
	$db->query("SHOW TABLES");
	while ($r = $db->fetch_row(0)) {
		$tables[ $r[0] ] = 1;
	} 
}

// first see if the database already exists
if (!$dbexists) {
	$dbcreated = $db->createdb($dbname, array('extra' => 'default character set=utf8'));
	$dbexists = ($dbcreated);
	$doinit = ($dbcreated);
	$overwrite = 0;
	$drop = 0;
	if (!$dbcreated) $dberr = $db->errstr;
	if ($dbexists) $db->selectdb($dbname);
}

// now, if the database exists or was created ... lets use it

// load our SQL schema 
$schema = preg_split('/;\s*/', 
	implode('', 
		array_map('rtrim', 
			preg_grep("/^(--|DROP|\\/\\*)/", file("$dbtype/basic.sql"), PREG_GREP_INVERT)
		)
	)
);
if (!$schema) err("Unable to read database schema for installation!");

// load our SQL defaults
$defaults = preg_split('/;\s*/', 
	implode('', 
		array_map('rtrim', 
			preg_grep("/^(--|(UN)?LOCK|\\/\\*)/", file("$dbtype/defaults.sql"), PREG_GREP_INVERT)
		)
	)
);
if (!$defaults) err("Unable to read schema defaults for installation!");

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<input name="submit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>STEP 3: Initializing Database</h3>
		<p>
		</p>
	</div>

<?php if (!$dbexists and !$dbcreated): ?>
	<div class="row row-err">
		Error creating database '<?php echo $dbname?>'<br><?php echo $dberr?>
	</div>

	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: left">
			Your database user probably does not have the proper privileges to "CREATE" databases or tables 
			on the server. If you've specified the wrong user information in the config.php you can 
			<a href="<?php echo $PHP_SELF?>?step=1">go back to step 1 and reconfigure</a>.
			<br><br>
			If you have access to a database management interface you can change the user privileges and then click
			the "Initialize Database" button below.
			<br><br>
			You could also manually edit the config.php on the server and verify your database user information
			is correct.
			<br><br>
			<b>You can not proceed past this step until the database is initialized!</b>
		</div>
		</div>
	</div>
<?php elseif ($dbexists and !$dbcreated): ?>
	<div class="row row-err-success">
		Database '<?php echo $dbname?>' already exists!<br>
		<?php // echo $totaltables ? "$totaltables tables already exist" : "No tables are in this database" ?>
	</div>
	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: left">
		<input id="dropdb" name="drop" value="1" type="checkbox" <?php echo ($drop) ? 'checked' : ''; ?> onclick="this.form.elements.tbloverwrite.disabled = (this.checked); web.getObj('tbloverwritelable').style.color = (this.checked) ? 'gray' : 'black';" />
		<label for="dropdb">DROP database first?<br>(warning: all existing data in database will be lost)</label>
		<br>

		<input id="tbloverwrite" name="overwrite" value="1" type="checkbox" <?php echo ($overwrite) ? 'checked' : ''; ?> />
		<label id="tbloverwritelable" for="tbloverwrite">Overwrite existing tables?<br>(warning: data in existing tables will be lost)</label>
	<?php if (!$doinit): ?>
		<br><br>
		<b>When ready click the "Initialize Database" button below!</b>
	<?php endif; ?>
		</div>
		</div>
	</div>
<?php elseif ($dbexists and $dbcreated): ?>
	<div class="row row-err-success">
		Database '<?php echo $dbname?>' was created successfully!<br>
	</div>
<?php endif; ?>

<?php if ($dbexists and $doinit): ?>
	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: left">

<?php 

$errors = 0;

// drop the current database if needed 
$format = "<div class='row'><div style='float: left'><b>%s database '%s'</div><div style='float: right'>[ <span style='color: %s'>%s</span> ]</b></div></div>\n";
if ($drop) {
	$overwrite = 0;
	$ok = $db->dropdb($dbname);
	$errors += !$ok ? 1 : 0;
	printf($format, "Dropping", $dbname, $ok ? "green" : "red", $ok ? "OK" : "ERROR");
	$ok = $db->createdb($dbname, array('extra' => 'default character set=utf8'));
	$errors += !$ok ? 1 : 0;
	printf($format, "Creating", $dbname, $ok ? "green" : "red", $ok ? "OK" : "ERROR");
	$db->selectdb($dbname);
	$tables = array();
}

// initialize the database schema
$format = "<div class='row'><div style='float: left'><b>%s table '%s'</div><div style='float: right'>[ <span style='color: %s'><acronym title='%s'>%s</acronym></span> ]</b></div><div style='clear:both'>%s</div></div>\n";
$wascreated = array();
$skipped = array();
foreach ($schema as $sql) {
	if (!$sql) continue;		// ignore blank lines

	// ignore table if the format of the sql is unknown
	if (!preg_match('/CREATE TABLE ([^\w])([\w\d_]+)\1/i', $sql, $m)) continue;
	$table = $m[2];

	if ($dbtblprefix != 'ps_') {	// fix the table names to use the proper prefix
		$table = preg_replace('/^ps_/', $dbtblprefix, $table);
		$sql = str_replace($m[2], $table, $sql);
	}

	// only drop the table if it existed when we initialized. To avoid confusion with 'unknown table' errors
	if ($overwrite and array_key_exists($table, $tables)) {
		$ok = $db->droptable($table);
		$errors += !$ok ? 1 : 0;
		$e = htmlentities(strip_tags($db->errstr), ENT_QUOTES);
		printf($format, "Dropping", $table, $ok ? "green" : "red", $e, $ok ? "OK" : "ERROR", $e);
	} elseif (!$overwrite and array_key_exists($table, $tables)) {
		$skipped[$table] = 1;
		$e = "Table exists; Not recreating it";
		printf($format, "Skipping", $table, "orange", $e, "OK", $e);
		continue;
	}

	$ok = $db->query($sql);
	$errors += !$ok ? 1 : 0;
	$wascreated[$table] = $ok;
	$e = htmlentities(strip_tags($db->errstr), ENT_QUOTES);
	printf($format, "Creating", $table, $ok ? "green" : "red", $e, $ok ? "OK" : "ERROR", $e);
}

// import defaults into database
$format = "<div class='row'><div style='float: left'><b>Initializing table '%s'</div><div style='float: right'>[ <span style='color: %s'><acronym title='%s'>%s</acronym></span> ]</b></div></div>\n";
foreach ($defaults as $sql) {
	if (!$sql) continue;		// ignore blank lines

	// ignore table if the format of the sql is unknown
	if (!preg_match('/INSERT INTO ([^\w])([\w\d_]+)\1/i', $sql, $m)) continue;
	$table = $m[2];

	if ($dbtblprefix != 'ps_') {	// fix the table names to use the proper prefix
		$table = preg_replace('/^ps_/', $dbtblprefix, $table);
		$sql = str_replace($m[2], $table, $sql);
	}

	// do not initialize a table if it wasn't created
	if (!$wascreated[$table]) continue;

	$ok = $db->query($sql);
	$errors += !$ok ? 1 : 0;
	printf($format, $table, $ok ? "green" : "red", htmlentities(strip_tags($db->errstr), ENT_QUOTES), $ok ? "OK" : "ERROR");
}

// !DEFAULTS!
// redo some defaults since the basic SQL file may still contain some values from my test database
if ($wascreated["${dbtblprefix}config"]) {
	$db->query("DELETE FROM ${dbtblprefix}config WHERE conftype='main' AND section='' AND var='logsource'");
	$db->query("DELETE FROM ${dbtblprefix}config WHERE conftype='servers'");
	$db->query("UPDATE ${dbtblprefix}config SET value='' WHERE conftype='theme' AND section='' AND var='compiledir'");
	$db->query("UPDATE ${dbtblprefix}config SET value='' WHERE conftype='theme' AND section='map' AND var='google_key'");
	$db->query("UPDATE ${dbtblprefix}config SET value='14' WHERE conftype='main' AND section='' AND var='maxdays'");
	$db->query("UPDATE ${dbtblprefix}config SET value='0' WHERE conftype='main' AND section='' AND var='maxdays_exclusive'");
	$db->query("UPDATE ${dbtblprefix}config SET value='0' WHERE conftype='main' AND section='errlog' AND var='report_unknown'");
	$db->query("UPDATE ${dbtblprefix}config SET value='0' WHERE conftype='info' AND section like 'daily_%'");
	$db->query("UPDATE ${dbtblprefix}config SET value='" . $db->escape($VERSION) . "' WHERE conftype='info' AND section=''");

	$db->optimize("${dbtblprefix}config");
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
<?php if (count($skipped)): ?>
			<b>Note:</b> Some tables were skipped since they exist already. If you need to fully reset your
			database you need to select the "Overwrite existing tables" or "DROP database first" options above.
			And then click the "Initialize Database" button again.
			<br>
<?php endif; ?>
			Please click the 'Continue' button below to proceed!
		</div>
		</div>
	</div>
<?php endif; ?>

	<div class="row-spacer"></div>
	<div class="row">
		<div class="form-actions">
			<div style="float: right">
<?php //if ($dbexists and !$dbcreated): ?>
				<input name="doinit" value="Initialize Database" type="submit" class="btn" />
<?php //endif; ?>
<?php //if ($dbdone or ($dbexists and !$dbcreated)): ?>
<?php if ($dbdone): ?>
				<input name="step4" value="Continue &gt; &gt;" type="submit" class="btn" />
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
