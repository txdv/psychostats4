<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include_once(PS_ROOTDIR . '/includes/forms.php');

$validfields = array('delicon', 'upload', 'download', 'ref', 'themefile');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if ($cancel) previouspage('index.php');
if (!user_logged_on()) gotopage("login.php?ref=" . urlencode($PHP_SELF . "?id=$id"));

// form fields ...
$formfields = array(
	'iconurl'	=> array('label' => $ps_lang->trans("Image URL"). ':', 	'val' => '', 'statustext' => $ps_lang->trans("Specify the URL of the image to download")),
	'iconfile'	=> array('label' => $ps_lang->trans("Upload Image"). ':', 	'val' => '', 'statustext' => $ps_lang->trans("Upload an icon from your computer")),
);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'uploadicon';
$data['page'] = 'uploadicon';

$form = array();
$errors = array();
$fatal_errors = array();
$success_msgs = array();

$icons = load_icons();
$icons_writable = is_writable(catfile($ps->conf['theme']['rootimagesdir'], 'icons'));
$allow_icon_upload = ($icons_writable && ($ps->conf['theme']['icons']['allow_upload'] || user_is_admin()));
$allow_icon_overwrite = ($ps->conf['theme']['icons']['allow_overwrite'] || user_is_admin());

// process submitted form
if ($submit and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);
//	if (get_magic_quotes_gpc()) stripslashes_all($form);
	$file = array();
	$from = '';

	if ($delicon) {
		if (user_is_admin()) {
			$deleted = array();
			$notdeleted = array();
			$unlinkerr = array();
			foreach ($delicon as $del) {
				if (!empty($icons[$del])) {
					if (!@unlink(catfile($ps->conf['theme']['rootimagesdir'], 'icons', $del))) {
						$unlinkerr[] = $del;
					} else {
						$deleted[] = $del;
					}
				} else {
					$notdeleted[] = $del;
				}
			}
			if (count($deleted)) {
				$icons = load_icons();
				$success_msgs[] = $ps_lang->trans("Icons deleted") . ": " . htmlentities(implode(', ', $deleted));
			}
			if (count($notdeleted)) {
				$fatal_errors[] = $ps_lang->trans("Invalid icons not deleted") . ": " . htmlentities(implode(', ', $notdeleted));
			}
			if (count($unlinkerr)) {
				$fatal_errors[] = $ps_lang->trans("Error deleting icons") . ": " . htmlentities(implode(', ', $unlinkerr));
			}
		} else {
			$fatal_errors[] = $ps_lang->trans("You do not have permission to delete icons");
		}

	} elseif ($download) {
		$from = 'iconurl';
		if (!empty($form['iconurl']) and !preg_match('|^\w+://|', $form['iconurl'])) {
			$form['iconurl'] = "http://" . $form['iconurl'];
		}
		if (($tmpname = @tempnam('/tmp', 'iconimg')) === FALSE) {
			$formfields['iconurl']['error'] = $ps_lang->trans("Unable to create temporary file for download");
		} else {
			$file['tmp_name'] = $tmpname;
			$file['name'] = basename($form['iconurl']);
			$dl = @fopen($form['iconurl'], 'rb');
			if (!$dl) {
				$formfields['iconurl']['error'] = $ps_lang->trans("Unable to download file from server");
			}
			$fh = @fopen($file['tmp_name'], 'wb');
			if (!$fh) {
				$formfields['iconurl']['error'] = $ps_lang->trans("Unable to process download");
			}
			if ($dl and $fh) {
				while (!feof($dl)) {
					fwrite($fh, fread($dl, 8192));
				}
				fclose($dl);
				fclose($fh);
				$file['info'] = getimagesize($file['tmp_name']);
			}
		}
	} elseif ($upload) {
		$from = 'iconfile';
		$file = $_FILES['iconfile'];
		$file['info'] = getimagesize($file['tmp_name']);
		if (!is_uploaded_file($file['tmp_name'])) {
			$formfields['iconfile']['error'] = $ps_lang->trans("Uploaded icon is invalid");
		}
	}

	if (!$delicon) {
		validate_img($file, $from);
		$errors = all_form_errors($formfields);

		// If there are no errors act on the data given
		if (!count($errors)) {
			$dir = catfile($ps->conf['theme']['rootimagesdir'], 'icons');
			$newfile = catfile($dir, $file['name']);
			if (!$allow_icon_overwrite and file_exists($newfile)) {
					$formfields[$from]['error'] = $ps_lang->trans("You do not have permission to overwrite existing icons");
			} else {
				if (!@copy($file['tmp_name'], $newfile)) {
					$formfields[$from]['error'] = $ps_lang->trans("Error saving image to icon directory");
				} else {
					if (empty($c)) {
						previouspage('index.php');
					} else {
						$icons = load_icons();
						$success_msgs[] = $ps_lang->trans("Icon uploaded") . ": " . htmlentities($file['name']);
					}
				}
			}
		}
		if ($download) @unlink($file['tmp_name']);	// remove temp file from download
	}

	$data += $form;	
}

$data['icons'] = $icons;
$data['allow_icon_upload'] = $allow_icon_upload;
$data['allow_icon_overwrite'] = $allow_icon_overwrite;
$data['form'] = $formfields;
$data['fatal_errors'] = $fatal_errors;
$data['success_msgs'] = $success_msgs;

$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include_once(PS_ROOTDIR . '/includes/footer.php');

function validate_img($file, $from) {
	global $formfields, $ps_lang, $ps;
	if ($formfields[$from]['error']) return;
	if (!$file['info'] or $file['info'][2] > 3) {
		$formfields[$from]['error'] = $ps_lang->trans("Image type is invalid");		
	} elseif ($file['size'] > $ps->conf['theme']['icons']['max_size']) {
		$formfields[$from]['error'] = $ps_lang->trans("Image size is too large") . " (" . abbrnum($file['size']) . ")";
	} elseif ($file['info'][0] > $ps->conf['theme']['icons']['max_width'] or $file['info'][1] > $ps->conf['theme']['icons']['max_width']) {
		$formfields[$from]['error'] = $ps_lang->trans("Image dimensions are too big") . " ({$file['info'][0]}x{$file['info'][1]})";
	}
}
?>
