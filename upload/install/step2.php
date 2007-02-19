<?php
/*
	STEP2: 
		Attempt to save database config or simply display it so the user can do it manually

*/
if (!defined("VALID_PAGE")) die("Go Away!");

$validvars = array('submit','back','dosave','step3','dbtype','dbhost','dbport','dbname','dbuser','dbpass','dbtblprefix');
globalize($validvars);

$dbconf = array(
	'dbtype'	=> $dbtype,
	'dbhost'	=> $dbhost,
	'dbport'	=> $dbport,
	'dbname'	=> $dbname,
	'dbuser'	=> $dbuser,
	'dbpass'	=> $dbpass,
	'dbtblprefix'	=> $dbtblprefix
);

if ($back) {
	gotopage("$PHP_SELF?step=1");
}
if ($step3) {
	gotopage("$PHP_SELF?step=3");
}

$config = "<?php
// Basic database and user configuration.
// All other configuration is stored in the database.

\$dbtype = '$dbtype';
\$dbhost = '$dbhost';
\$dbport = '$dbport';
\$dbname = '$dbname';
\$dbuser = '$dbuser';
\$dbpass = '$dbpass';
\$dbtblprefix = '$dbtblprefix';

\$userhandler = 'normal';

?>";

$file = str_replace('\\','/',dirname(dirname(__FILE__))) . "/config.php";
$savemsg = '';
$saved = saveconf($file, $config, $savemsg);

// if we saved and we haven't manually submitted yet, just go to the next step
if ($saved and !$submit) {
	gotopage("$PHP_SELF?step=3");
}

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
defaultTextStr = '';
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<?php echo savepost($dbconf)?>
<input name="submit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>STEP 2: Saving Database Configuration</h3>
		<p>
		</p>
	</div>

	<div class="row">
<?php if ($saved): ?>
		<b>Success:</b> <?php echo $savemsg?><br>
		Click the 'Continue' button to proceed
<?php else: ?>
		<center><div class="row row-err"><b>Error:</b> <?php echo $savemsg?></div></cemter>
		<div align="center">
		<div style="width: 375px; text-align: justify">
			The most common cause of not being able to save the configuration file is because the 
			USER that runs the web server does not have the proper permissions to write to the file.
			<br><br>
			<b>You must do one of the following steps manually:</b>

<?php if ($is_windows): ?>
			<li>Your web server is a Windows server so you need to change the permissions on the directory
			where the config.php is located so that the USER_ANONYMOUS user can write to it.
			<i>These instructions are not correct and have not been verified or tested yet</i>
<?php else: ?>
			<li>Your web server is linux so you should be able to login as your user and change the permissions 
			on the config.php file.
			This usually means allowing the 'world' to write to the file (mode: 666). 
			After logging into the server with SSH or Telnet and changing the directory to where the config.php is, 
			type this command:
			<blockquote><i>chmod 666 config.php</i></blockquote>
			After doing that click the "Save Config" button below.
			<br><br>You can change the permissions back to normal after you complete this installation step:
			<blockquote><i>chmod 644 config.php</i></blockquote>
<?php endif; ?>
			<li><b>Alternatively</b>, you can copy the config contents below and paste it into the config.php 
			file on the server. Click the 'Continue' button below to proceed after you save the config.php.
			<br><br>
			It's very important that you do not include any extra spaces before the "&lt;?php" or after the "?&gt;".
		</div>
		</div>
		<br><b>Proposed Config:</b><br>
		<textarea name="confblock" rows="15" cols="70" class="field" onfocus="this.select()"><?php echo htmlentities($config)?></textarea>
<?php endif; ?>
	</div>

	<div class="row-spacer"></div>
	<div class="row">
		<div class="form-actions">
			<div style="float: right">
<?php if (!$saved): ?>
				<input value="Save Config" type="submit" class="btn" />
<?php endif; ?>
				<input name="step3" value="Continue &gt; &gt;" type="submit" class="btn" />
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


