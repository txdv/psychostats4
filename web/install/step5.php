<?php
/*
	STEP5: 
		Create the main 'admin' user

*/
if (!defined("VALID_PAGE")) die("Go Away!");

include("../config.php");
require_once("../includes/class_DB.php");

$validvars = array('submit','step6');
globalize($validvars);

// form fields ...
$formfields = array(
	'username'  => array('label' => 'Username:', 		'val' => 'B', 'statustext' => "Enter the admin username"),
	'password1' => array('label' => 'Password:', 		'val' => 'B', 'statustext' => "Enter a password"),
	'password2' => array('label' => 'Retype Password:', 	'val' => 'B', 'statustext' => "Retype the same password again"),
);

if ($back) {
	gotopage("$PHP_SELF?step=1");
}
if ($step6) {
	gotopage("$PHP_SELF?step=6");
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
$adminexists = '';
$admincreated = 0;
$table = $dbtblprefix . "user";

if ($db) {
	list($adminexists) = $db->fetch_row(0, "SELECT " . $db->qi('username') ." FROM $table WHERE " . $db->qi('accesslevel') . " >= " . ACL_ADMIN);
} else {
	$fatal = $db->errstr;

}

if ($submit) {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	if ($db->exists($table, 'username', $form['username'])) {
		$formfields['username']['error'] = "Username already exists!";
	}

	if ($form['password1'] != $form['password2']) {
		$formfields['password1']['error'] = "Passwords do not match! Try again";
		$formfields['password2']['error'] = "";
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = array(
			'userid'	=> $db->next_id($table, 'userid'),
			'lastvisit'	=> time(),
			'session_last'	=> time(),
			'accesslevel'	=> defined("ACL_ADMIN") ? ACL_ADMIN : 10,
			'username' 	=> $form['username'], 
			'password' 	=> md5($form['password1']),
			'confirmed'	=> 1
		);
		$ok = $db->insert($table, $set);
		if ($ok) {
			$admincreated = 1;
			$adminexists = $form['username'];
		} else {
			$fatal = $db->errstr;
		}
	}
}

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<input name="submit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>STEP 5: Creating Administrator User</h3>
		<p>
			The admin will have access to everything within the stats website.
			The admin is not associated with any player.
		</p>
	</div>

<?php if ($fatal): ?>
	<div class="row row-err"><?php echo $fatal?></div>
<?php endif; ?>

<?php if ($adminexists or $admincreated): ?>
	<div class="row row-err-success">
	<?php if ($admincreated): ?>
		The admin user <b>'<?php echo htmlentities($adminexists)?>'</b> was created successfully!<br>
		You can create another admin user if you need.
	<?php else: ?>
		An admin user <b>'<?php echo htmlentities($adminexists)?>'</b> already exists.<br>
		You can create another admin below if required.
	<?php endif; ?>
		<br><br>Click the 'Continue' button below when you are done creating admins.
	</div>
	<div class="row-spacer"></div>
<?php endif; ?>

	<div class="row<?php echo $formfields['username']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['username']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['username']['label'])?>
		</span>
		<span class="input">
			<input name="username" value="<?php echo htmlentities($form['username'])?>" type="text" size="30" class="field" />
			<?php if ($formfields['username']['error']) print "<p class='err'>".htmlentities($formfields['username']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['password1']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['password1']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['password1']['label'])?>
		</span>
		<span class="input">
			<input name="password1" type="password" size="30" class="field" />
			<?php if ($formfields['password1']['error']) print "<p class='err'>".htmlentities($formfields['password1']['error'])."</p>"?>
		</span>
	</div>

	<div class="row<?php echo $formfields['password2']['error'] ? ' row-err' : ''?>">
		<span onmouseover="statusText('<?php echo preg_replace("%(?<!\\\\)'%", "\\'", $formfields['password2']['statustext'])?>')" class="label">
			<?php echo htmlentities($formfields['password2']['label'])?>
		</span>
		<span class="input">
			<input name="password2" type="password" size="30" class="field" />
			<?php if ($formfields['password2']['error']) print "<p class='err'>".htmlentities($formfields['password2']['error'])."</p>"?>
		</span>
	</div>

	<div class="row-spacer"></div>
	<div class="row">
		<div class="form-actions">
			<div style="float: right">
				<input name="save" value="Save Admin" type="submit" class="btn" />
<?php if ($adminexists): ?>
				<input name="step6" value="Continue &gt; &gt;" type="submit" class="btn" />
<?php endif; ?>
			</div>
		</div>
	</div>
	<?php include('block_form_footer.html'); ?>
</form>
<div class="spacer"></div>
</div>

