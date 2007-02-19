<?php
/*
	STEP6: 
		uhhh.... we're done!

*/
if (!defined("VALID_PAGE")) die("Go Away!");

$statsurl = str_replace('\\','/', dirname(dirname($PHP_SELF)));
if (substr($statsurl,-1) != '/') $statsurl .= '/';

if (!defined('PSYCHOSTATS_INSTALLED')) {
	$today = date("Y-m-d H:i:s");
	// resave the configuration with the install flag set so the installer will no longer function
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

define('PSYCHOSTATS_INSTALLED', '$today');

?>";

	$file = str_replace('\\','/',dirname(dirname(__FILE__))) . "/config.php";
	$savemsg = '';
	$saved = saveconf($file, $config, $savemsg);
}

?>
<script language="javascript" type="text/javascript">
<?php include("form_js.html"); ?>
defaultTextStr = 'Installation Complete';
</script>
<div class="divtable shade1">
<form name="config" action="<?php echo $PHP_SELF?>" method="POST">
<input name="step" value="<?php echo htmlentities($step)?>" type="hidden" />
<input name="submit" value="1" type="hidden" />
	<div class="table-hdr">
		<h3>PsychoStats Installation Finished!</h3>
		<p>
		</p>
	</div>

	<div class="row">
		<div align="center">
		<div style="width: 375px; text-align: justify">
			<b>PsychoStats online installation is now complete! Congratulations!</b>

			<p style="border: 1px solid red; background-color: #ffdddd; padding: 5px">
			<span style="color: red; font-weight: bold">Security Note:</span><br>
			Once you have verified your PsychoStats installation is working you should DELETE this install directory! 
			Or a malicious user could potentionally destroy your database (<i>and not just the PsychoStats 
			database!</i>).
			</p>
			The first thing you should do is go to the main stats page by <a href="<?php echo $statsurl?>login.php?ref=<?php echo urlencode("admin.php?c=logsource")?>" target="_blank">clicking here</a>
			and logging in with the admin account you just created and viewing the default configuration under the
			'Admin' -&gt; 'Main Config' section.
			<br><br>
			You want to make sure your configuration is setup the way you want it. The first item you need to configure
			is the <b>'logsource'</b> which defines where your game server logs are located. 
			<br><br>
			If you require assistance please visit the <a href="http://www.psychostats.com/" target="_blank">PsychoStats</a> forums.
			Or you can visit us in our <a href="irc:/irc.gamesurge.net/psychostats">#psychostats</a> IRC channel on gamesurge.net.
			Please be sure to detail exactly what your problem is, what you've done to troubleshoot and the environment
			in which you're trying to install PsychoStats.
			<br><br>
			<b>What's Next?</b><hr>
			In order for the player stats to start collecting you need to run the stats.pl. If you're going to do 
			that on THIS server then just go to the directory where you unzipped PsychoStats and run the stats.pl.
			Otherwise, if you're going to do this on another server (usually the game server) you will need to 
			install the local portion of PsychoStats on that server. First unzip PsychoStats on the server and 
			then edit the stats.cfg to use the proper database settings. Run the stats.pl to update the stats and 
			that's it!
			<br><br>
		</div>
		</div>
	</div>
	<?php include('block_form_footer.html'); ?>
</form>
<div class="spacer"></div>
</div>


