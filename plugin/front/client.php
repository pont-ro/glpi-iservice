<?php

// Imported from iService2, needs refactoring.

define('GLPI_ROOT', '../../..');
include_once GLPI_ROOT . '/inc/includes.php';

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\Views;

$partner = PluginIservicePartner::getFromMagicLink();
$magic_link = IserviceToolBox::getInputVariable('id');

if (!isset($_SESSION['magic_link_access'][$magic_link])) {
	$client_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
	$_SESSION['magic_link_access'][$magic_link] = $client_ip;
	if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
		$download = new PluginIserviceDownload(PluginIserviceDownload::DOWNLOAD_TYPE_MAGIC_LINK);
		$download->add(array(
				'items_id' => $partner->getID(),
				'ip' => $_SESSION['magic_link_access'][$magic_link]));
	}
}

if (Session::getLoginUserID()) {
	// if user is logged in, we don't need cui authentication
	$cui = empty($partner) ? null : $partner->customfields->fields['uic_field'];
} else {
	$cui = IserviceToolBox::getInputVariable('cui');
}

// If cui is not entered, request authentication
if (empty($cui)) {
	Html::header('', filter_input(INPUT_SERVER, 'PHP_SELF'));
	$html = new Html();
	$html->openForm(array('method' => 'post'));
	echo "Vă rugăm introduceți Codul Unic de Înregistrare (CUI): ";
	echo "<input type='text' name='cui'> ";
	echo "<input type='submit' class='submit' name='go' value='mai departe'>";
	$html->closeForm();
	Html::footer();
	return;
}

// Check cui authentication
if (strpos($cui, 'RO') === 0) {
	$cui = substr($cui, strlen ('RO'));
}
$partner_cui = $partner->customfields->fields['uic_field'];
if (strpos($partner_cui, 'RO') === 0) {
	$partner_cui = substr($partner_cui, strlen ('RO'));
}
if ($cui !== $partner_cui) {
	Html::header('iService', filter_input(INPUT_SERVER, 'PHP_SELF'));
	echo "<div style='display:none'>$cui (cui) != $partner_cui (partner_cui)</div>";
	Html::displayRightError();
}

// All good, we can display client page
Html::header($partner->fields['name'], filter_input(INPUT_SERVER, 'PHP_SELF'), $partner);

if (!empty($partner)) {
	$view = Views::getView(IserviceToolBox::getInputVariable('view', 'facturi_client'), false);
	$view->customize(array('client_access'=>true,'partner'=>$partner));
	PluginIserviceProfile::checkRight($view->getRightName());
	$view->display();
}

Html::footer();
