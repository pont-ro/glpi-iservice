<?php

// Imported from iService2.
require "../inc/includes.php";
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$magic_link = IserviceToolBox::getInputVariable('id', null);
$nrfac      = IserviceToolBox::getInputVariable('nrfac', null);

global $DB;
$right_check_query = "
	SELECT count(*)
	FROM glpi_plugin_fields_suppliersuppliercustomfields pc
	LEFT JOIN hmarfa_facturi fa ON fa.codbenef = pc.hmarfa_code_field
	WHERE pc.magic_link_field = '$magic_link'
		AND fa.nrfac = '$nrfac'
	";

if (!$DB->result($DB->query($right_check_query), 0, 0)) {
    Html::displayNotFoundError();
}

$download = new PluginIserviceDownload();
$download->setDownloadType(PluginIserviceDownload::DOWNLOAD_TYPE_INVOICE);
if (!$download->exists($nrfac)) {
    Html::displayNotFoundError();
}

$file_path      = $download->getPath($nrfac);
$file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

header("Cache-Control: public"); // Needed for i.e.
header("Content-Transfer-Encoding: Binary");
header("Content-Type: application/$file_extension");
header("Content-Disposition: attachment; filename=$nrfac.$file_extension");
$file_data = file_get_contents($file_path);
if ($file_data === false) {
}

$download->add(['items_id' => $nrfac]);
echo $file_data;
