<?php

// Imported from iService2, needs refactoring. Original file: "generate_ssx.php".
// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "generate_ssx.php")) {
    include '../../../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

function getSubtotalRowData($last_data, $last_cost_centre, $subtotal, $header_count)
{
    $result = [
        $last_data[0] ?? '',
        $last_data[1] ?? '',
        $last_data[2] ?? '',
        $last_data[3] ?? '',
        'S039-G',
        1,
        0,
        "* * * * * * * * * * * * * Subtotal centru de cost $last_cost_centre = $subtotal",
    ];
    if ($header_count > 8) {
        $result[8] = $last_cost_centre;
    }

    return $result;
}

$path            = PluginIserviceCommon::getInputVariable('path');
$file_name_parts = explode('.', PluginIserviceCommon::getInputVariable('file_name'), 2);

$result = '';
foreach (['S.' . $file_name_parts[1], 'SX.' . $file_name_parts[1]] as $file_name) {
    if (!file_exists("$path$file_name")) {
        echo "File $path$file_name does not exist";
        die;
    }

    if (($ssx_handle = fopen("{$path}S$file_name", "w")) === false) {
        echo "Could not open file {$path}S$file_name for writing";
        die;
    }

    if (($sx_handle = fopen("$path$file_name", "r")) === false) {
        echo "Could not open file $path$file_name for reading";
        die;
    }

    $row              = 0;
    $matches          = '';
    $subtotal         = 0;
    $last_data        = [];
    $header_count     = 0;
    $last_cost_centre = '';
    while (($data = fgetcsv($sx_handle, 0, ",")) !== false) {
        $row++;
        if (count($data) < 8) {
            $result .= "Eroare date in rândul $row.\n";
            fputcsv($ssx_handle, $data);
            continue;
        }

        if ($row === 1 || $data[4] === 'S039-S') {
            fputcsv($ssx_handle, $data);
            $header_count = count($data);
            continue;
        }

        if ($header_count > 8) {
            $new_cost_centre = $data[8];
        } elseif (preg_match('#\(\*([^\*]*)\*#', $data[7], $matches)) {
            $new_cost_centre = $matches[1];
        } else {
            $result .= "Rândul $row nu conține numele centrului de cost.<br>";
            fputcsv($ssx_handle, $data);
            continue;
        }

        if ($new_cost_centre !== $last_cost_centre) {
            if (!empty($last_cost_centre)) {
                fputcsv($ssx_handle, getSubtotalRowData($last_data, $last_cost_centre, $subtotal, $header_count));
            }

            $subtotal = 0;
        }

        $last_data        = $data;
        $last_cost_centre = $new_cost_centre;
        $subtotal        += $data[5] * $data[6];
        fputcsv($ssx_handle, $data);
    }

    fputcsv($ssx_handle, getSubtotalRowData($last_data, $last_cost_centre, $subtotal, $header_count));

    fclose($sx_handle);
    fclose($ssx_handle);
}

if (empty($result)) {
    echo PluginIserviceCommon::RESPONSE_OK;
} else {
    echo $result;
}
