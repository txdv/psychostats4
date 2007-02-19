<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

// if gametype is maliciously changed into a filepath,
// basename() will make sure it remains 'sane'.
$g = basename($this->conf['main']['gametype']);
$m = basename($this->conf['main']['modtype']);
if (@file_exists("includes/PS/$g/$m.php")) include("includes/PS/$g/$m.php");

?>
