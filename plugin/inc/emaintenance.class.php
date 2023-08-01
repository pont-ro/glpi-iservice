<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceEmaintenance extends MailCollector
{

    const DEFAULT_EMAIL = 'emaintenance@expertline.ro';

    const ACCEPTED_SENDERS = ['exlemservice@gmail.com', 'sendonly@rcm.ec1.srv.ygles.com'];

    protected $protectedStorage;

    public static function getTable($classname = null): string
    {
        if (empty($classname)) {
            $classname = 'MailCollector';
        }

        return parent::getTable($classname);
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('E-maintenance', 'E-maintenance', $nb, 'iservice');
    }

    /**
     * Cron action on em_mailgate : retrieve mail and create tickets
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    public static function cronEm_Mailgate($task): int
    {

        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.em_mailgate'))) {
            $task->log("E-maintenance mailgate is disabled by configuration.\n");
            return -2;
        }

        $comment_parts      = explode("\n", $task->fields['comment'], 2);
        $mailcollector_data = self::getMailCollector(trim($comment_parts[0]));

        if (empty($mailcollector_data)) {
            $task->log("Cannot collect emaintenance mails from $comment_parts[0]\n");
            return -1;
        }

        $task->log("Collect emaintenance mails from $mailcollector_data[name]\n");

        $em                  = new self();
        $em->maxfetch_emails = $task->fields['param'];
        $message             = $em->collect($mailcollector_data["id"]);
        $task->addVolume($em->fetch_emails);
        $task->log("$message\n");

        if (empty($em->fetch_emails)) {
            return 0; // Nothing to do.
        } elseif ($em->fetch_emails < $em->maxfetch_emails) {
            return 1; // Done.
        }

        return -1; // There are more messages to retrieve.
    }

    public static function getMailCollector($email = ''): array
    {
        if (empty($email)) {
            $email = self::DEFAULT_EMAIL;
        }

        return PluginIserviceDB::getQueryResult("SELECT * FROM `glpi_mailcollectors` WHERE `name` = '$email'", false)[0] ?? [];
    }

    public static function getCsvConfig($type = 'EM'): array
    {
        return [
            'EM' => [
                'columns' => [
                    'id' => 2,
                    'partner_name' => 0,
                    'partner_id' => 1,
                    'date_out' => 4,
                    'date_use' => 5,
                    'data_luc' => 6,
                    'c102' => 8, // Not sent anymore, but we need this to check that the line contains enough data.
                    'c106' => 7, // * 106
                    'c109' => 8, // * 109
                ],
                'min_columns' => 4
            ],
            'IW' => [
                'columns' => [
                    'id' => 2,
                    'c102' => 7,
                    'c106' => 10, // * 122
                    'c106plus' => 11, // * 123
                    'c109' => 8, // * 112
                    'c109plus' => 9, // * 113
                ],
                'min_columns' => 3
            ],
            'AVITUM' => [
                'columns' => [
                    'id' => 3,
                    'partner_name' => 0,
                    'date_out' => 5,
                    'date_use' => 6,
                    'data_luc' => 7,
                    'c102' => 8,
                    'c106' => 9,
                    'c109' => 10,
                ],
                'min_columns' => 4
            ]
        ][$type];
    }

    public static function getDataFromCsvsForSpacelessSerial($spaceless_serial, $file_names = []): array|bool
    {
        return self::getDataFromCsvs($file_names, [$spaceless_serial])[$spaceless_serial] ?? false;
    }

    public static function getDataFromCsvs($file_names = [], $limit_serials = []): array
    {
        if (empty($file_names)) {
            $file_names = self::getImportFilePaths();
        }

        $data = [];

        foreach (array_reverse($file_names) as $file_name) {
            foreach (self::getDataFromCsv($file_name, 'EM', $limit_serials) as $spaceless_serial => $counter_data) {
                // If we have no data so far for this serial, we save the data.
                if (empty($data[$spaceless_serial])) {
                    $data[$spaceless_serial] = $counter_data;
                    continue;
                }

                $overwrite = false;
                // If we have newer valid data or the new and old data are both invalid, we overwrite.
                foreach (['date_out', 'date_use', 'total2_black', 'total2_color'] as $fieldName) {
                    if (!empty($counter_data[$fieldName]) && (empty($counter_data[$fieldName]['error']) || !empty($data[$spaceless_serial][$fieldName]['error']))) {
                        $data[$spaceless_serial][$fieldName] = $counter_data[$fieldName];
                        $overwrite                           = true;
                    }
                }

                // If we overwrote something, we overwrite the data_luc also.
                if ($overwrite && !empty($counter_data['data_luc']) && (empty($counter_data['data_luc']['error']) || !empty($data[$spaceless_serial]['data_luc']['error']))) {
                    $data[$spaceless_serial]['data_luc'] = $counter_data['data_luc'];
                }
            }
        }

        return $data;
    }

    public static function getDataFromCsv($file_name, $type = 'EM', $limit_serials = []): ?array
    {
        $csv_config = self::getCsvConfig($type);

        if (!is_file($file_name) || false === ($handle = fopen($file_name, "r"))) {
            return null;
        }

        if (!is_array($limit_serials)) {
            $limit_serials = [$limit_serials];
        }

        $result = [];
        while (false !== ($data = fgetcsv($handle, 0, ","))) {
            $data_count = count($data);
            if ($data_count < $csv_config['min_columns']) {
                continue;
            }

            $id = str_replace(" ", "", $data[$csv_config['columns']['id']]);

            if (!empty($limit_serials) && !in_array($id, $limit_serials)) {
                continue;
            }

            if (!empty($result[$id])) {
                $result[$id] = [
                    'total2_black' => '#empty#import#data#',
                    'total2_color' => '#empty#import#data#',
                    'data_luc' => '#empty#import#data#',
                    'error' => "Există mai multe rânduri pentru seria $id"
                ];
                continue;
            }

            $result[$id] = [
                'total2_black' => '#empty#import#data#',
                'total2_color' => '#empty#import#data#',
                'data_luc' => '#empty#import#data#',
            ];
            $printer     = new PluginIservicePrinter();
            if (!$printer->getFromDBByEMSerial($id)) {
                $result[$id]['error'] = "Nu există aparat cu seria $id";
                continue;
            }

            if ((!$printer->isColor() && $data_count < $csv_config['columns']['c102'] + 1) || $data_count < $csv_config['columns']['c109'] + 1) {
                $result[$id]['error'] = 'Rând incomplet în CSV';
                continue;
            }

            $result[$id]['partner_name'] = $data[$csv_config['columns']['partner_name'] ?? -1] ?? $printer->fields['supplier_name'];

            $data_luc           = null;
            $date_use           = null;
            $date_out           = null;
            $total2_black       = null;
            $total2_color       = null;
            $data_luc_error     = null;
            $total2_black_error = null;
            $total2_color_error = null;

            if ($type == 'IW') {
                $file_name_date_time = substr($_FILES['iwm_import_file']['name'], 0, 12);
                if ('2000-01-01' > ($data_luc = date('Y-m-d H:i:s', strtotime($file_name_date_time)))) {
                    $data_luc       = '';
                    $data_luc_error = $file_name_date_time . " nu este o dată validă";
                }

                if (is_numeric($data[$csv_config['columns']['c109']]) && is_numeric($data[$csv_config['columns']['c109plus']])) {
                    $total2_black = $data[$csv_config['columns']['c109']] * 2 + $data[$csv_config['columns']['c109plus']];
                } else {
                    $total2_black       = false;
                    $total2_black_error = "Valoarea \"{$data[$csv_config['columns']['c109']]}\" sau \"{$data[$csv_config['columns']['c109plus']]}\" pentru contorul 109 nu este număr";
                }

                if ($printer->isColor()) {
                    if (is_numeric($data[$csv_config['columns']['c106']]) && is_numeric($data[$csv_config['columns']['c106plus']])) {
                        $total2_color = $data[$csv_config['columns']['c106']] * 2 + $data[$csv_config['columns']['c106plus']];
                    } elseif (!is_numeric($data[$csv_config['columns']['c102']]) || ($total2_black === false)) {
                        $total2_color       = false;
                        $total2_color_error = "Contorul color nu poate fi calculat din diferența contoarelor 102 ({$data[$csv_config['columns']['c102']]}) și 109 ({$data[$csv_config['columns']['c109']]} * 2 + {$data[$csv_config['columns']['c109plus']]})";
                    } else {
                        $total2_color = $data[$csv_config['columns']['c102']] - $total2_black;
                    }
                }
            } elseif ($type == 'AVITUM') {
                if (false === ($data_luc_time = self::getDateTimeFromString($data[$csv_config['columns']['data_luc']]))) {
                    $data_luc       = '';
                    $data_luc_error = $data[$csv_config['columns']['data_luc']] . " nu este o dată validă";
                } else {
                    $data_luc = date('Y-m-d H:i:s', $data_luc_time->getTimestamp());
                }

                if (false !== ($date_use_time = self::getDateTimeFromString($data[$csv_config['columns']['date_use']]))) {
                    $date_use = date('Y-m-d', $date_use_time->getTimestamp());
                } else {
                    $result[$id]['warning'][] = 'Data instalării aparatului este invalidă: ' . $data[$csv_config['columns']['date_use']];
                }

                if ($printer->isColor()) {
                    $data[$csv_config['columns']['c106']] = str_replace(',', '', $data[$csv_config['columns']['c106']]);
                    $data[$csv_config['columns']['c109']] = str_replace(',', '', $data[$csv_config['columns']['c109']]);

                    if (is_numeric($data[$csv_config['columns']['c106']])) {
                        $total2_color = $data[$csv_config['columns']['c106']];
                    } else {
                        $total2_color       = false;
                        $total2_color_error = "Valoarea \"{$data[$csv_config['columns']['c106']]}\" pentru contorul 106 nu este număr";
                    }

                    if (is_numeric($data[$csv_config['columns']['c109']])) {
                        $total2_black = $data[$csv_config['columns']['c109']];
                    } else {
                        $total2_black       = false;
                        $total2_black_error = "Valoarea \"{$data[$csv_config['columns']['c109']]}\" pentru contorul 109 nu este număr";
                    }
                } else {
                    $data[$csv_config['columns']['c102']] = str_replace(',', '', $data[$csv_config['columns']['c102']]);

                    if (is_numeric($data[$csv_config['columns']['c102']])) {
                        $total2_black = $data[$csv_config['columns']['c102']];
                    } else {
                        $total2_black       = false;
                        $total2_black_error = "Valoarea \"{$data[$csv_config['columns']['c102']]}\" pentru contorul 102 nu este număr";
                    }
                }
            } else {
                if (false === ($data_luc_time = self::getDateTimeFromString($data[$csv_config['columns']['data_luc']]))) {
                    $data_luc       = '';
                    $data_luc_error = $data[$csv_config['columns']['data_luc']] . " nu este o dată validă";
                } else {
                    $data_luc = date('Y-m-d H:i:s', $data_luc_time->getTimestamp());
                }

                if (false !== ($date_use_time = self::getDateTimeFromString($data[$csv_config['columns']['date_use']]))) {
                    $date_use = date('Y-m-d', $date_use_time->getTimestamp());
                } else {
                    $result[$id]['warning'][] = 'Data instalării aparatului este invalidă: ' . $data[$csv_config['columns']['date_use']];
                }

                // Black counter is counter 109.
                $total2_black_error = ($total2_black = is_numeric($data[$csv_config['columns']['c109']]) ? $data[$csv_config['columns']['c109']] : false) === false ? "Valoarea \"{$data[$csv_config['columns']['c109']]}\" pentru contorul 109 nu este număr" : null;

                if ($printer->isColor()) {
                    // Color counter is counter 106.
                    $total2_color_error = ($total2_color = is_numeric($data[$csv_config['columns']['c106']]) ? $data[$csv_config['columns']['c106']] : false) === false ? "Valoarea \"{$data[$csv_config['columns']['c106']]}\" pentru contorul 106 nu este număr" : null;
                }
            }

            $error = false;
            foreach (['date_use', 'data_luc', 'total2_black', 'total2_color'] as $field_name) {
                $error_variable_name = $field_name . "_error";
                if ($$field_name === null && empty($$error_variable_name)) {
                    continue;
                }

                $result[$id][$field_name] = empty($$error_variable_name) ? $$field_name : ['error' => $$error_variable_name];
                $error                   |= !empty($$error_variable_name);
            }

            if ($error && !isset($result[$id]['data_luc']['error'])) {
                $result[$id]['data_luc'] = '#empty#import#data#';
            }

            $result[$id]['date_out']              = '??';
            $result[$id]['partner_id']            = '??';
            $result[$id]['partner_resolved_name'] = '??';
            if ($date_use && !empty($data[$csv_config['columns']['date_out']])) {
                if (false !== ($date_out_time = self::getDateTimeFromString($data[$csv_config['columns']['date_out']]))) {
                    $date_out                = date('Y-m-d', $date_out_time->getTimestamp());
                    $result[$id]['date_out'] = $date_out;
                }

                if ($date_out === null) {
                    continue;
                } elseif ($date_out < $date_use) {
                    $result[$id]['data_luc'] = '#empty#import#data#';
                    $result[$id]['error']    = "Aparatul a fost predat înainte să fie instalat!";
                    continue;
                }

                $supplier = new Supplier();
                if (!$supplier->getFromDB($data[$csv_config['columns']['partner_id'] ?? -1] ?? $printer->fields['supplier_id'])) {
                    // Change this to get the supplier from infocom.
                        $result[$id]['data_luc'] = '#empty#import#data#';
                        $result[$id]['error']    = "Partenerul nu poate fi identificat";
                        continue;
                }

                $result[$id]['partner_resolved_name'] = $supplier->fields['name'];

                $printer_movements = PluginIserviceDB::getQueryResult(
                    "
                    select *
                    from glpi_plugin_iservice_movements m
                    join glpi_plugin_fields_ticketticketcustomfields cft on cft.movement_id_field = m.id
                    where m.itemtype = 'Printer'
                      and m.items_id = {$printer->getID()}
                      and m.suppliers_id_old = {$supplier->getID()}
                      and m.init_date > '$date_use'
                "
                );
                if (count($printer_movements) < 1) {
                    $result[$id]['data_luc'] = '#empty#import#data#';
                    $result[$id]['error']    = "Nu există mutare cu tichet retragere creat mai recent de $date_use,\ncare retrage aparatul {$printer->fields['name']} de la {$supplier->fields['name']},\ndeși acesta a fost predat la data de $date_out!";
                    continue;
                }
            }
        }

        fclose($handle);

        return $result;
    }

    public static function getImportControl($button_caption, $import_file_path, $button_name = 'import', $input_name = 'import_file'): string
    {
        $real_import_file_path = self::getImportFilePath($import_file_path);
        $last_import_file_time = filemtime($real_import_file_path);
        return "
                <span style='margin-bottom:.2em;'>
                    <input type='text' name='$input_name' value='$real_import_file_path' style='width:250px;'> (din " . date('Y-m-d H:i:s', $last_import_file_time) . ")
                    <input type='submit' class='submit' name='$button_name' value='$button_caption'>
                </span>";
    }

    public static function getIwmImportControl($button_caption, $button_name = 'iwm_import', $input_name = 'iwm_import_file'): string
    {
        return "
                <span style='margin-bottom:.2em;'>
                    <input type='file' name='$input_name' accept='.csv' style='border: 0px;'> 
                    <input type='submit' class='submit' name='$button_name' value='$button_caption'>
                </span>";
    }

    public static function getAvitumImportControl($button_caption, $button_name = 'avitum_import'): string
    {
        return "
                <span style='margin-bottom:.2em;'>
                    <input type='submit' class='submit' name='$button_name' value='$button_caption'>
                </span>";
    }

    public static function getImportFilePath($import_file_path = ''): ?string
    {
        $import_file_paths = self::getImportFilePaths($import_file_path);
        return array_shift($import_file_paths);
    }

    public static function getImportFilePaths($import_file_path = ''): string|array
    {
        if (is_file($import_file_path)) {
            return [$import_file_path];
        }

        if (is_dir($import_file_path)) {
            $import_path = $import_file_path;
        } else {
            $import_path = PluginIserviceConfig::getConfigValue('emaintenance.import_default_path');
        }

        $file_paths = [];
        foreach (glob("$import_path/*.csv") as $file_path) {
            if (!is_file($file_path)) {
                continue;
            }

            $file_paths[filemtime($file_path)] = $file_path;
        }

        krsort($file_paths);
        return array_slice($file_paths, 0, 3, true);
    }

    /*
     * @return \PluginIservicePrinter
     */
    public static function getPrinterFromEmailData($mail_data): ?PluginIservicePrinter
    {
        $extended_data = self::getExtendedMailData($mail_data);
        if (!empty($extended_data['body_lines']['rds id'])) {
            $serial = $extended_data['body_lines']['rds id']['ending'];
        } elseif (!empty($extended_data['body_lines']['device id'])) {
            $serial = $extended_data['body_lines']['device id']['ending'];
        } else {
            $serial = end($extended_data['subject_parts']);
        }

        $printer = new PluginIservicePrinter();
        if ($printer->getFromDBByEMSerial($serial, true)) {
            return $printer;
        } elseif ($printer->getFromDBByEMSerial($serial, false)) {
            $printer->no_cm = 1;
            return $printer;
        } else {
            return null;
        }
    }

    public static function getContentForTicket($mail_data, $html_format = true): string
    {
        $line_beginnings = [
            'alarm code' => 'first',
            'specified status' => 'first',
            'toner item no' => 'first',
            'toner mercury code' => 'first',
            'jam code' => 'first',
            'error code' => 'first',
            'description' => 'last',
            'details' => 'last',
        ];

        $result        = [];
        $end_result    = [];
        $extended_data = isset($mail_data['body_lines']) ? $mail_data : PluginIserviceEmaintenance::getExtendedMailData($mail_data);
        $body_lines    = $extended_data['body_lines'];

        foreach ($body_lines as $line_beginning => $line_data) {
            if (empty($line_beginnings[$line_beginning])) {
                continue;
            }

            $formated_line = ($html_format ? "<b>" : "") . $line_data['beginning'] . ($html_format ? "</b>" : "") . ": " . $line_data['ending'];
            switch ($line_beginnings[$line_beginning]) {
            case 'first':
                $result[] = $formated_line;
                break;
            case 'last':
                $end_result[] = $formated_line;
                break;
            default:
                break;
            }
        }

        $line_break = $html_format ? "<br />" : "\n";
        return implode($line_break, $result) . (empty($end_result) ? '' : (empty($result) ? '' : $line_break) . implode($line_break, $end_result));
    }

    public static function getExtendedMailData($mail_data): array
    {
        $mail_data['subject_parts'] = array_map('trim', explode('/', $mail_data['subject']));
        foreach (explode("\n", $mail_data['body']) as $line) {
            if (empty($mail_data['body_lines'])) {
                $mail_data['body_lines']['first'] = trim($line);
                continue;
            }

            if (stripos($line, 'http') === 0) {
                $mail_data['body_lines']['link'] = trim($line);
                continue;
            }

            $parts = explode(':', trim($line), 2);
            if (count($parts) < 2) {
                continue;
            }

            $mail_data['body_lines'][strtolower(trim($parts[0]))] = [
                'line' => trim($line),
                'beginning' => trim($parts[0]),
                'ending' => trim($parts[1]),
            ];
        }

        return $mail_data;
    }

    /**
     * Collects and processes the E-maintenance emails from the account given by the mailcollector.
     *
     * @param $id      ID of the mailcollector
     * @param $display display messages in MessageAfterRedirect or just return error (default 0=)
     *
     * @return if $display = false return messages result string
     * */
    public function collect($id, $display = 0): ?string
    {
        if (!$this->getFromDB($id)) {
            // TRANS: %s is the ID of the mailgate.
            $msg = sprintf(__('Could not find mailgate %d'), $id);
            return $display ? Session::addMessageAfterRedirect($msg, false, ERROR) : $msg;
        }

        if (!empty($this->fields['is_active'])) {
            $msg = __('MailCollector has to be inactive to use it for E-maintenance', 'iservice');
            return $display ? Session::addMessageAfterRedirect($msg, false, WARNING) : $msg;
        }

        $this->uid          = -1;
        $this->fetch_emails = 0;

        try {
            // Connect to the Mail Box.
            $this->connect();
        } catch (Throwable $e) {
            $msg = __('Could not connect to mailgate server') . '<br/>' . $e->getMessage();
            return $display ? Session::addMessageAfterRedirect($msg, false, ERROR) : $msg;
        }

        $rejected = new NotImportedEmail();
        // Clean from previous collect (from GUI, cron already truncate the table).
        $rejected->deleteByCriteria(['mailcollectors_id' => $this->getID()]);

        // Get Total Number of Unread Email in mailbox.
        $count_messages = $this->getTotalMails(); // Total Mails in Inbox Return integer value.
        $result         = ['error' => 0, 'refused' => 0, 'imported' => 0, 'auto_processed' => 0];

        do {
            $this->protectedStorage->rewind();
            if (!$this->protectedStorage->valid()) {
                break;
            }

            try {
                $this->fetch_emails++;
                $result[$this->processEmail($this->protectedStorage->getUniqueId($this->protectedStorage->key()), $this->protectedStorage->current())] += 1;
            } catch (\Exception $e) {
                Toolbox::logInFile('mailgate', sprintf(__('Message is invalid: %1$s') . '<br/>', $e->getMessage()));
                $result['error'] += 1;
            }
        } while ($this->protectedStorage->valid() && ($this->fetch_emails < $this->maxfetch_emails));

        // TRANS: %1$d, %2$d, %3$d, %4$d and %5$d are number of messages.
        $msg = sprintf(
            __('Number of messages: available=%1$d, already imported=%2$d, retrieved=%3$d, refused=%4$d, errors=%5$d, blacklisted=%6$d'),
            $count_messages,
            0,
            $result['imported'] + $result['auto_processed'],
            $result['refused'],
            $result['error'],
            '?' // $blacklisted
        );
        return $display ? Session::addMessageAfterRedirect($msg, false, ($result['error'] ? ERROR : INFO)) : $msg;
    }

    /**
     * Processes the email given by the uid.
     *
     * @param string $uid The uid of the message from the storage.
     * @param \Laminas\Mail\Storage\Message $message Message.
     *
     * @return string error, refused, imported or auto_processed
     */
    protected function processEmail(string $uid, \Laminas\Mail\Storage\Message $message): string
    {
        $mail_data = $this->getMailData($message);
        $result    = null;
        try {
            if (false !== ($refuse_reason = $this->refuseMailData($mail_data))) {
                $result = 'refused';
                throw new Exception($refuse_reason);
            }

            if (false !== ($ememail_id = $this->importMailData($mail_data))) {
                $result = 'imported';
            }

            if ($this->autoProcessMailData($ememail_id, $mail_data)) {
                $result = 'auto_processed';
            }

            $this->deleteMails($uid, self::ACCEPTED_FOLDER);
            return empty($result) ? 'error' : $result;
        } catch (Exception $ex) {
            $not_imported_email = new NotImportedEmail();
            $not_imported_email->add(
                [
                    'from' => $mail_data['from'],
                    'to' => $mail_data['to'],
                    'mailcollectors_id' => $this->getID(),
                    'date' => $_SESSION["glpi_currenttime"],
                    'subject' => $ex->getMessage(),
                    'messageid' => $mail_data['message_id'],
                    'reason' => NotImportedEmail::MATCH_NO_RULE
                ]
            );
            $this->deleteMails($uid, self::REFUSED_FOLDER);
            return empty($result) ? 'error' : $result;
        }

    }

    protected function getMailData(\Laminas\Mail\Storage\Message $message): array
    {
        $headers = $this->getHeaders($message);

        $body = $this->getBody($message);
        // if (!empty($this->charset) && !$this->body_converted && mb_detect_encoding($body) != 'UTF-8') {
        // $body = Toolbox::encodeInUtf8($body, $this->charset);
        // $this->body_converted = true;
        // }
        if (!Toolbox::seems_utf8($body)) {
            $body = Toolbox::encodeInUtf8($body);
        }

        $subject = $this->cleanSubject($headers['subject']);
        if (!Toolbox::seems_utf8($subject)) {
            $subject = Toolbox::encodeInUtf8($subject);
        }

        return [
            'message_id' => $headers['message_id'],
            'date' => $this->fields['use_mail_date'] ? $headers['date'] : $_SESSION["glpi_currenttime"],
            'from' => $headers['from'],
            'to' => $headers['to'],
            'subject' => $subject,
            'body' => $body
        ];
    }

    protected function refuseMailData($mail_data): string
    {
        if (empty($mail_data['from']) || !in_array($mail_data['from'], self::ACCEPTED_SENDERS)) {
            return sprintf(__('Email rejected from %s', 'iservice'), $mail_data['from']);
        }

        if ($mail_data['subject'] == 'Reminder Notification for Consumable Event') {
            return sprintf(__('Email with subject "%s" rejected', 'iservice'), $mail_data['subject']);
        }

        return false;
    }

    protected function importMailData(&$mail_data): int|bool
    {
        $ememail                = new PluginIserviceEMEmail();
        $mail_data['suggested'] = '';
        if (null !== ($printer = self::getPrinterFromEmailData($mail_data))) {
            $mail_data['printer']                  = $printer;
            $mail_data['printers_id']              = $printer->getID();
            $mail_data['printer_spaceless_serial'] = $printer->getSpacelessSerial();
            $mail_data['in_cm']                    = empty($printer->no_cm);
            $mail_data['users_id_tech']            = $printer->fields['users_id_tech'];
            $infocom                               = new Infocom();
            if ($infocom->getFromDBforDevice('Printer', $printer->getID())) {
                $mail_data['suppliers_id'] = $infocom->fields['suppliers_id'];
            }
        }

        return $ememail->add($mail_data);
    }

    public static function getAutoProcessRules(): array
    {
        return [
            'description' => [
                'Waste toner' => 'autoAddTicket',
                'Unidentified Toner Cartridge replacement' => 'autoAddTonerBottleReplacementTicket',
                'Toner bottle replacement notification' => 'autoAddTonerBottleReplacementTicket',
            ],
            'details' => [
                'Toner Cartridge replacement' => 'autoAddTonerBottleReplacementTicket',
                'Toner bottle replacement' => 'autoAddTonerBottleReplacementTicket',
                'Toner (Bk) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner (C) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner (M) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner (Y) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner Low' => 'autoAddTicketIfNoChangeableCartridge',
            ],
            'specified status' => [
                'Toner (Bk) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner (C) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner (M) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner (Y) prior delivery alarm' => 'autoAddTicketIfNoChangeableCartridge',
                'Toner Low' => 'autoAddTicketIfNoChangeableCartridge',
            ],
        ];
    }

    protected function autoProcessMailData($ememail_id, &$mail_data): bool
    {
        $extended_data = PluginIserviceEmaintenance::getExtendedMailData($mail_data);

        $rules = self::getAutoProcessRules();

        foreach ($rules as $message_line => $message_line_rules) {
            foreach ($message_line_rules as $condition => $function_to_call) {
                if (!empty($extended_data['body_lines'][$message_line]) && stripos($extended_data['body_lines'][$message_line]['line'], $condition) && method_exists($this, $function_to_call)) {
                    return (bool) $this->$function_to_call($ememail_id, $extended_data);
                }
            }
        }

        return false;
    }

    protected function autoAddTonerBottleReplacementTicket($ememail_id, $extended_data)
    {
        if (false === ($ticket = $this->autoAddTicket($ememail_id, $extended_data, ['_users_id_assign' => PluginIserviceTicket::USER_ID_READER]))) {
            return false;
        }

        // Add cartridge to the ticket.
        return $this->autoAddCatridgeToTonerBottleReplacementTicket($ticket, $ememail_id, $extended_data);
    }

    protected function autoAddCatridgeToTonerBottleReplacementTicket($ticket, $ememail_id, $extended_data)
    {
        $ememail = new PluginIserviceEMEmail();
        if (empty($extended_data['body_lines']['toner mercury code'])) {
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => "Could not detect Toner Mercury Code"]);
            return false;
        }

        $cartridge_item_ids    = PluginIserviceCartridgeItem::getIdsByMercuryCode($extended_data['body_lines']['toner mercury code']['ending']);
        $changeable_cartridges = PluginIserviceCartridgeItem::getChangeablesForTicket($ticket);
        $cartridge_item_index  = $this->getCartridgeItemIndex($cartridge_item_ids, $changeable_cartridges);

        $error_message = '';
        if (empty($cartridge_item_ids)) {
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => "Cartridge with mercury code {$extended_data['body_lines']['toner mercury code']['ending']} could not be found in iService"]);
            return false;
        } elseif ($cartridge_item_index === null) {
            $error_message .= "Cartridge with mercury code {$extended_data['body_lines']['toner mercury code']['ending']} could not be added to the ticket as it is not compatible or stock is 0";
            $error_message .= "###Changeable cartridges:<pre>" . print_r($changeable_cartridges, true) . "</pre>";
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => $error_message]);
            return false;
        } elseif (!$ticket->addCartridge($error_message, $cartridge_item_index, $extended_data['suppliers_id'], $extended_data['printers_id'])) {
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => $error_message]);
            return false;
        }

        return true;
    }

    protected function autoAddTicketIfNoChangeableCartridge($ememail_id, $extended_data): bool
    {
        if (empty($ememail_id)) {
            return false;
        }

        $ememail = new PluginIserviceEMEmail();
        $ticket  = new PluginIserviceTicket();
        $errors  = [];

        if (empty($extended_data['printers_id'])) {
            $errors[] = 'Could not detect printer';
        }

        if (empty($extended_data['in_cm'])) {
            $errors[] = 'Printer not in CM';
        }

        if (empty($extended_data['suppliers_id'])) {
            $errors[] = 'Could not detect partner for printer';
        }

        if (empty($extended_data['body_lines']['toner mercury code'])) {
            $errors[] = "Could not detect Toner Mercury Code";
        }

        if (!empty($errors)) {
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => implode('<br>', $errors)]);
            return false;
        }

        $ticket->fields['items_id']['Printer'][0] = $extended_data['printers_id'];
        if (!empty($extended_data['suppliers_id'])) {
            // This field value will be needed to get the changeable cartridges.
            $ticket->fields['_suppliers_id_assign'] = $extended_data['suppliers_id'];
        }

        $cartridge_item_ids   = PluginIserviceCartridgeItem::getIdsByMercuryCode($extended_data['body_lines']['toner mercury code']['ending']);
        $cartridge_item_index = $this->getCartridgeItemIndex($cartridge_item_ids, $ticket);

        if (empty($cartridge_item_ids)) {
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => "Cartridge with mercury code {$extended_data['body_lines']['toner mercury code']['ending']} could not be found in iService"]);
            return false;
        } elseif ($cartridge_item_index === null) {
            // If there is no changeable cartridge, create a ticket.
            if (!$this->autoAddTicket($ememail_id, $extended_data)) {
                return false;
            }
        }

        // In any success case the mail should be marked as read.
        $ememail->update(['id' => $ememail_id, 'read' => 1]);

        return true;
    }

    protected function autoAddTicket($ememail_id, $extended_data, $override_data = []): PluginIserviceTicket|bool
    {
        if (empty($ememail_id)) {
            return false;
        }

        $ememail = new PluginIserviceEMEmail();
        $ticket  = new PluginIserviceTicket();
        $errors  = [];

        if (empty($extended_data['printers_id'])) {
            $errors[] = 'Could not detect printer';
        }

        if (empty($extended_data['in_cm'])) {
            $errors[] = 'Printer not in CM';
        }

        if (empty($extended_data['suppliers_id'])) {
            $errors[] = 'Could not detect partner for printer';
        }

        if (!empty($errors)) {
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => implode('<br>', $errors)]);
            return false;
        }

        // Prepare ticket data.
        $ticket->prepareForShow(['mode' => PluginIserviceTicket::MODE_CREATENORMAL]);
        $ticket->explodeArrayFields();
        $data_luc    = self::getDateTimeFromString($extended_data['body_lines']['occurred']['ending'] ?? '') ?: self::getDateTimeFromString($extended_data['date']);
        $ticket_data = [
            // This field value will be needed to get the changeable cartridges.
            'items_id' => ['Printer' => [$extended_data['printers_id']]],
            'locations_id' => $extended_data['printer']->fields['locations_id'],
            '_users_id_assign' => $extended_data['users_id_tech'],
            'name' => $extended_data['subject_parts'][0],
            'content' => self::getContentForTicket($extended_data, false),
            '_idemmailfield' => $ememail_id,
            '_without_moving' => 1,
            '_without_papers' => 1,
            'data_luc' => $data_luc->format('Y-m-d H:i:s'),
        ];
        if (!empty($extended_data['suppliers_id'])) {
            // This field value will be needed to get the changeable cartridges.
            $ticket_data['_suppliers_id_assign'] = $extended_data['suppliers_id'];
        }

        $csv_data = self::getDataFromCsvs();
        if (!empty($csv_data[$extended_data['printer_spaceless_serial']])) {
            if (empty($csv_data[$extended_data['printer_spaceless_serial']]['total2_black']['error'])) {
                $ticket->fields['total2_black'] = $csv_data[$extended_data['printer_spaceless_serial']]['total2_black'];
            }

            if (empty($csv_data[$extended_data['printer_spaceless_serial']]['total2_color']['error'])) {
                $ticket->fields['total2_color'] = $csv_data[$extended_data['printer_spaceless_serial']]['total2_color'];
            }
        }

        // Add ticket.
        $merged_ticket_data = array_merge($ticket->fields, $ticket_data);
        if (!empty($override_data) && is_array($override_data)) {
            $merged_ticket_data = array_merge($merged_ticket_data, $override_data);
        }

        if (!$ticket->add(array_merge($merged_ticket_data, ['add' => 'add', '_mode' => PluginIserviceTicket::MODE_CREATENORMAL, '_no_message' => 1]))) {
            $errors[] = "Could not automatically create ticket###Ticket data:<pre>" . print_r($merged_ticket_data, true) . "</pre>";
            $ememail->update(['id' => $ememail_id, 'read' => 0, 'process_result' => implode('<br>', $errors)]);
            return false;
        }

        $ememail->update(['id' => $ememail_id, 'read' => 1]);

        return $ticket;
    }

    protected function getCartridgeItemIndex($cartridge_item_ids, $from_object): ?string
    {
        $cartridge_item_index = null;
        if (!is_array($cartridge_item_ids)) {
            $cartridge_item_ids = [$cartridge_item_ids];
        }

        if ($from_object instanceof PluginIserviceTicket) {
            $changeable_cartridges = PluginIserviceCartridgeItem::getChangeablesForTicket($from_object);
        } elseif (is_array($from_object)) {
            $changeable_cartridges = $from_object;
        } else {
            $changeable_cartridges = [];
        }

        foreach ($cartridge_item_ids as $cartridge_item_id) {
            foreach ($changeable_cartridges as $changeable_cartridge) {
                if (isset($changeable_cartridge['id']) && $changeable_cartridge['id'] == $cartridge_item_id) {
                    $cartridge_item_index = $changeable_cartridge['id'] . (empty($changeable_cartridge['location_name']) ? '' : ("l" . $changeable_cartridge['FK_location']));
                    break;
                }
            }
        }

        return $cartridge_item_index;
    }

    protected static function getDateTimeFromString($string): DateTime|bool
    {
        $formats = [
            'm-d-Y H:i A',
            'm-d-Y H:i',
            'm-d-Y H:i:s',
            'm-d-Y',
            'm/d/Y H:i A',
            'm/d/Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y',
        ];
        foreach ($formats as $format) {
            if (false !== ($result = DateTime::createFromFormat($format, $string))) {
                return $result;
            }
        }

        return false;
    }

    public function connect(): void
    {
        $config = Toolbox::parseMailServerConnectString($this->fields['host']);

        $params = [
            'host'      => $config['address'],
            'user'      => $this->fields['login'],
            'password'  => (new GLPIKey())->decrypt($this->fields['passwd']),
            'port'      => $config['port']
        ];

        if ($config['ssl']) {
            $params['ssl'] = 'SSL';
        }

        if ($config['tls']) {
            $params['ssl'] = 'TLS';
        }

        if (!empty($config['mailbox'])) {
            $params['folder'] = $config['mailbox'];
        }

        if ($config['validate-cert'] === false) {
            $params['novalidatecert'] = true;
        }

        try {
            $storage = Toolbox::getMailServerStorageInstance($config['type'], $params);
            if ($storage === null) {
                throw new \Exception(sprintf(__('Unsupported mail server type:%s.'), $config['type']));
            }

            $this->protectedStorage = $storage;
            if ($this->fields['errors'] > 0) {
                $this->update(
                    [
                        'id'     => $this->getID(),
                        'errors' => 0
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->update(
                [
                    'id'     => $this->getID(),
                    'errors' => ($this->fields['errors'] + 1)
                ]
            );
            // Any errors will cause an Exception.
            throw $e;
        }
    }

}
