<?php

require '../inc/includes.php';

function getSearchStrings()
{
    $searchStrings = [
        'PluginIserviceCommon::getQueryResult' => 'PluginIserviceDB::getQueryResult',
        'PluginIserviceCommon' => 'IserviceToolBox',
        'PluginFieldsSuppliercustomfield' => 'PluginFieldsSuppliersuppliercustomfield',
        'PluginFieldsPrintermodelcustomfield' => 'PluginFieldsPrintermodelprintermodelcustomfield',
        'PluginFieldsTicketcustomfield' => 'PluginFieldsTicketticketcustomfield',
        'PluginFieldsPrintercustomfield' => 'PluginFieldsPrinterprintercustomfield',
        'PluginFieldsContractcustomfield' => 'PluginFieldsContractcontractcustomfield',
        'PluginFieldsCartridgeitemcustomfield' => 'PluginFieldsCartridgeitemcartridgeitemcustomfield',
        'PluginFieldsCartridgecustomfield' => 'PluginFieldsCartridgecartridgecustomfield',
        'clickable' => 'pointer',
        'getFromDBByItemsId' => 'populateByItemsId???',
        'getFromDBByQuery' => 'populateByQuery???',
        'include (\'../../../inc/includes.php\')' => 'require \'../inc/includes.php\';',
        '$CFG_GLPI[root_doc]/plugins/iservice' => '$CFG_PLUGIN_ISERVICE[root_doc]',
        'DIRECTORY_SEPARATOR' => "'/'",
        'PluginIserviceHtml' => 'Html',
    ];

    $customFieldDefinitionFiles = glob(PLUGIN_ISERVICE_DIR . '/install/customfields/*.json');

    foreach ($customFieldDefinitionFiles as $customFieldDefinitionFile) {
        $fieldMap = json_decode(file_get_contents($customFieldDefinitionFile), true);
        foreach ($fieldMap as $field) {
            if (!isset($field['type']) || $field['type'] == 'header') {
                continue;
            }

            $searchStrings[$field['old_name']] = $field['name'];
        }
    }

    return $searchStrings;
}

$filename      = '';
$searchStrings = [];

$results         = [];
$selectedResults = [];

// Check if the form has been submitted.
if (null !== ($filename = filter_input(INPUT_POST, 'filename'))) {

    // Open the file for reading.
    $file           = fopen($filename, 'r');
    $updatedContent = '';

    if ($file) {
        define('GLPI_KEEP_CSRF_TOKEN', true);
        $searchStrings = getSearchStrings();
        // Get the selected results (array keys) from the form.
        $selectedResults = $_POST['selectedResults'] ?? [];

        $lineNumber = 0;

        // Loop through each line in the file.
        while (($line = fgets($file)) !== false) {
            $lineNumber++;

            // Check each search string (array key).
            foreach ($searchStrings as $key => $value) {
                if (in_array($lineNumber, $selectedResults) && strpos($line, $key) !== false) {
                    // If the array key is found and selected, add the modified line with the key replaced by its value.
                    $line = str_replace($key, $value, $line);
                }

                if (strpos($line, $key) !== false) {
                    // If the array key is found, add the line and line number to the results array.
                    $results[] = [
                        'lineNumber' => $lineNumber,
                        'content' => $line,
                        'search' => $key,
                        'replace' => $value,
                    ];
                }
            }

            $updatedContent .= $line;
        }

        fclose($file);

        // Open the file for writing and overwrite its content with the updated content.
        $file = fopen($filename, 'w');
        if ($file) {
             fwrite($file, $updatedContent);
             fclose($file);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP File Content Search and Replace</title>
</head>
<body>
<style>
    .search {
        background-color: palevioletred; /* Change this to the desired highlight color */
        font-weight: bold; /* You can adjust other styles as needed */
    }
    .replace {
        background-color: greenyellow; /* Change this to the desired highlight color */
        font-weight: bold; /* You can adjust other styles as needed */
    }
</style>
    <?php
    // Display the form only if it has not been submitted.
    if (empty($results)) {
        ?>
    <form method="POST" action="">
        <label for="filename">File Name:</label>
        <input type="text" id="filename" name="filename" value="<?php echo htmlspecialchars($filename); ?>" size="150" required><br>
        <input type='hidden' name='_glpi_csrf_token' value='<?php echo Session::getNewCSRFToken(); ?>'/>
        <input type="submit" value="Search">
    </form>
        <?php
    }
    ?>

    <?php
    // Display search results.
    if (!empty($results)) {
        echo '<h2>Search Results:</h2>';
        echo '<form method="POST" action="">';
        echo '<input type="text" id="filename" name="filename" value="' . htmlspecialchars($filename) . '" size="150" required><br>';
        echo '<input type="checkbox" id="checkAll" checked> Check All<br><br><br>';
        $lastLineNumber = 0;
        foreach ($results as $result) {
            $lineNumber      = $result['lineNumber'];
            $content         = htmlspecialchars($result['content']);
            $checkedDisabled = $lineNumber != $lastLineNumber ? 'checked' : 'disabled';
            $lastLineNumber  = $lineNumber;

            if (strpos($content, $result['search']) !== false) {
                // Highlight the matched text using CSS styles.
                $content = str_replace($result['search'], '<span class="search">' . $result['search'] . '</span>/<span class="replace">' . $result['replace'] . '</span>', $content);
            }

            echo '<input type="checkbox" class="checkbox" name="selectedResults[]" value="' . htmlspecialchars($result['lineNumber']) . '" ' . "$checkedDisabled>";
            echo 'Line ' . $lineNumber . ': ' . $content . "<br>";
            $contentReplaced = null;
        }

        echo "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'/>";
        echo '<input type="submit" value="Replace">';
        echo '</form>';
    }
    ?>

<script>
    const checkAllCheckbox = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.checkbox');

    checkAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = checkAllCheckbox.checked;
        });
    });
</script>
</body>
</html>

