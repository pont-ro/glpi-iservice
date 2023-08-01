<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceDownload extends CommonDBTM
{

    const DOWNLOAD_TYPE_INVOICE           = 'invoice';
    const DOWNLOAD_TYPE_INVOICE_CONFIRMED = 'invoice_confirmed';
    const DOWNLOAD_TYPE_MAGIC_LINK        = 'magic_link_field';
    const DOWNLOAD_TYPE_PARTNER_CONTACTED = 'partner_contacted';

    public $download_type;
    public $paths;
    public static $settings = [
        self::DOWNLOAD_TYPE_INVOICE => [
            'path' => ['/var/sambadir/2x/[year]/02_Facturi_electronice/', 'path' => 'C:\\tmp\\'],
            'start_year' => 2014,
        ],
        self::DOWNLOAD_TYPE_INVOICE_CONFIRMED => [],
        self::DOWNLOAD_TYPE_MAGIC_LINK => [],
        self::DOWNLOAD_TYPE_PARTNER_CONTACTED => [],
    ];

    public function __construct()
    {
        $this->paths = [];
    }

    public function setDownloadType($download_type)
    {
        if (array_key_exists($download_type, self::$settings)) {
            $this->download_type = $download_type;
        } else {
            $this->download_type = null;
        }
    }

    public function exists($id): bool
    {
        if (!empty($this->paths[$this->download_type]) && array_key_exists($id, $this->paths[$this->download_type]) && file_exists($this->paths[$this->download_type][$id])) {
            return true;
        }

        switch ($this->download_type) {
        case self::DOWNLOAD_TYPE_INVOICE:
            $settings = self::$settings[$this->download_type];
            foreach ($settings['path'] as $path) {
                if (strpos($path, '[year]')) {
                    $year = $settings['start_year'];
                } else {
                    $year = date('Y');
                }

                while ($year < date('Y') + 1) {
                    foreach (glob(str_replace('[year]', $year++, $path) . "?$id*") as $file_name) {
                        $this->paths[$this->download_type][$id] = $file_name;
                        return true;
                    }
                }
            }
            return false;
        default:
            return false;
        }
    }

    public function getPath($id): string|bool
    {
        if ($this->exists($id)) {
            return $this->paths[$this->download_type][$id];
        }

        return false;
    }

    public function add(array $input, $options = [], $history = true): int
    {
        if (!isset($input['downloadtype'])) {
            $input['downloadtype'] = $this->download_type;
        }

        if (!isset($input['ip'])) {
            $input['ip'] = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        }

        if (!isset($input['users_id'])) {
            $input['users_id'] = isset($_SESSION['glpiID']) ? $_SESSION['glpiID'] : 0;
        }

        return parent::add($input, $options, $history);
    }

}
