<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIservicePartner extends Supplier
{
    use PluginIserviceItem;

    const ID_EXPERTLINE = 525;

    const INVOICEINFO_FULL_UNPAID  = -2;
    const INVOICEINFO_FULL         = -1;
    const INVOICEINFO_DEBT         = 0;
    const INVOICEINFO_UNPAID_COUNT = 1;

    /*
     *
     *
     * @var PluginFieldsSuppliercustomfield
     */
    public $customfields = null;

    /*
     *
     *
     * @var array
     */
    public $hMarfa_fields = null;

    static function getType()
    {
        return Supplier::getType();
    }

    static function getTable($classname = null)
    {
        return Supplier::getTable($classname);
    }

    static function getFormURL($full = true)
    {
        return parent::getFormURL($full);
    }

    function getFromDB($ID)
    {
        $this->customfields  = new PluginFieldsSuppliercustomfield();
        $this->hMarfa_fields = [];
        if (parent::getFromDB($ID)) {
            if (!$this->customfields->getFromDBByItemsId($ID) && !$this->customfields->add(['add' => 'add', 'items_id' => $ID, '_no_message' => true])) {
                return false;
            }

            $this->hMarfa_fields = self::gethMarfaFields($this->customfields->fields['cod_hmarfa']);

            self::$item_cache[$ID] = $this;
            return true;
        }

        return false;
    }

    public function hasCartridgeManagement()
    {
        return !empty($this->customfields->fields['cartridge_management']);
    }

    static function gethMarfaFields($cod_hmarfa)
    {
        global $DB;
        if (($query_result = $DB->query("select * from hmarfa_firme where cod = '$cod_hmarfa'")) !== false && $query_result->num_rows > 0) {
            return $query_result->fetch_assoc();
        } else {
            return [];
        }
    }

    /*
     *
     * @param  array $input The input array.
     * @return PluginIservicePartner
     */
    public static function getFromTicketInput($input)
    {
        $supplier_id = PluginIserviceCommon::getValueFromInput('_suppliers_id_assign', $input);

        if (empty($supplier_id)) {
            return new self();
        }

        if (empty(self::$item_cache[$supplier_id])) {
            $supplier = new self();
            if ($supplier->getFromDB($supplier_id)) {
                self::$item_cache[$supplier_id] = $supplier;
            } else {
                self::$item_cache[$supplier_id] = false;
                return new self();
            }
        }

        return self::$item_cache[$supplier_id];
    }

    /*
     *
     * @return PluginIservicePartner
     */
    static function getFromMagicLink()
    {
        $magic_link           = PluginIserviceCommon::getInputVariable('id', null);
        $partner_customfields = new PluginFieldsSuppliercustomfield();
        if (!$partner_customfields->getFromDBByQuery("WHERE magic_link = '$magic_link' LIMIT 1")) {
            return null;
        }

        $partner = new PluginIservicePartner();
        return $partner->getFromDB($partner_customfields->fields['items_id']) ? $partner : null;
    }

    static function generateNewMagicLink($id)
    {
        if ($id instanceof PluginIservicePartner) {
            $partner = $id;
            $partner->check($id->getID(), UPDATE);
        } else {
            $partner = new PluginIservicePartner();
            $partner->check($id, UPDATE);
        }

        return $partner->customfields->update(
            [
                $partner->customfields->getIndexName() => $partner->customfields->getID(),
                'magic_link' => base64_encode(mt_rand(10000, 99999) . str_pad($id, 5, '0', STR_PAD_LEFT) . mt_rand(10000, 99999))
            ]
        );
    }

    function getMagicLink()
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . "/plugins/iservice/front/client.php?id=" . $this->customfields->fields['magic_link'];
    }

    function getInvoiceInfo($invoice_info = 0)
    {
        global $DB;
        $conditions = [
            "(codl = 'F' OR stare like 'V%') AND tip like 'TF%'",
            "codbenef = '{$this->customfields->fields['cod_hmarfa']}'",
        ];
        switch ($invoice_info) {
        case self::INVOICEINFO_DEBT:
            $select = "SUM(valinc-valpla) DEBT";
            break;
        case self::INVOICEINFO_UNPAID_COUNT:
            $select       = "COUNT(nrfac) COUNT";
            $conditions[] = "valinc-valpla > 0";
            break;
        case self::INVOICEINFO_FULL_UNPAID:
            $conditions[] = "valinc-valpla > 0";
        default:
            $select = "*";
            break;
        }

        $debt_query = "SELECT $select FROM hmarfa_facturi WHERE " . join(" AND ", $conditions);

        if ($invoice_info >= 0) {
            return $DB->result($DB->query($debt_query), 0, 0);
        }

        $result = [];
        if (($query_result = $DB->query($debt_query)) !== false) {
            while (($row = $query_result->fetch_array()) !== null) {
                $result[] = $row;
            }
        }

        return $result;
    }

    function getMailBody($type = '', $url_encoded = true)
    {
        if (!empty($this->customfields->fields['magic_link'])) {
            $unpaid_invoices_count = $unpaid_invoices_value = 0;
            $unpaid_invoices       = $this->getInvoiceInfo(PluginIservicePartner::INVOICEINFO_FULL_UNPAID);
            if (count($unpaid_invoices) < 1) {
                $unpaid_invoices_list = "";
            } else {
                $unpaid_invoices_list = "\r\nLista facturilor neachitate:";
                foreach ($unpaid_invoices as $unpaid_invoice) {
                    $unpaid_invoices_count += 1;
                    $unpaid_invoices_value += $unpaid_invoice_value = $unpaid_invoice['valinc'] - $unpaid_invoice['valpla'];
                    $unpaid_invoices_list  .= "\r\n- $unpaid_invoice[nrfac] din " . date("d.m.Y", strtotime($unpaid_invoice['datafac'])) . ", valoare / rest de plata: $unpaid_invoice_value lei";
                }

                if (strlen($unpaid_invoices_list) > 460) {
                    $unpaid_invoices_list = substr($unpaid_invoices_list, 0, 460) . '...';
                }
            }

            switch ($type) {
            case 'scadente':
                $mail_body = "Catre
{$this->fields['name']}
Cod fiscal: {$this->customfields->fields['part_cui']}

Stimate client,

Va anuntam ca la data de " . date("d.m.Y") . " figurati in evidentele noastre cu urmatoarele datorii:
Numar facturi neachitate: $unpaid_invoices_count
Valoare facturi neachitate: $unpaid_invoices_value
$unpaid_invoices_list

In cazul in care debitul mentionat mai sus a fost deja achitat, va rugam sa ignorati acest mesaj.
Pentru lamuriri suplimentare sau inadvertente va rugam sa luati legatura cu persoana dvs. de contact din cadrul companiei noastre, sau rsapundeti la acest mail

Pentru a vedea situatia ultimelor 10 facturi si a le descarca in format pdf, accesati link-ul urmator:
http://iservice2.expertline-magazin.ro{$this->getMagicLink()}

Va rog pastrati acest link deoarece datele de pe acest link vor fi in permananenta actualizate, deci va putea fi folosit pe viitor.
In cazul in care vi se cere o parola suplimentara pentru a accesa serverul Expert Line va rugam trimiteti o solicitare pe SMS sau WhatsApp la numarul 0722323366


Rugam confirmare de primire.

Cu stima,
Serviciul Financiar";
                break;
            case 'hMarfa':
            default:
                $mail_body = "Buna ziua,

Va atasam factura pt serviciile curente
Rog confirmare de primire.

In evidentele noastre figureaza urmatoarele datorii, inclusiv cea atasata
{$this->fields['name']}
Cod fiscal: {$this->customfields->fields['part_cui']}
Numar facturi neachitate: $unpaid_invoices_count
Valoare facturi neachitate: $unpaid_invoices_value
$unpaid_invoices_list

Pentru a vedea situatia ultimelor 10 facturi si a le descarca in format pdf, accesati link-ul urmator:
http://iservice2.expertline-magazin.ro{$this->getMagicLink()}

Va rog pastrati acest link deoarece datele de pe acest link vor fi in permananenta actualizate, deci va putea fi folosit pe viitor.
In cazul in care vi se cere o parola suplimentara pentru a accesa serverul Expert Line va rugam trimiteti o solicitare pe SMS sau WhatsApp la numarul 0722323366

Cu multumiri,
Expert Line srl
";
                break;
            }
        } else {
            $mail_body = "";
        }

        return $url_encoded ? str_replace("+", " ", urlencode($mail_body)) : $mail_body;
    }

    function displayPreferenceData()
    {
        if (Session::getLoginUserID()) {
            $class = '';
        } else {
            $class = " class='wide'";
        }

        echo "<script>$('#c_preference').addClass('no-user');</script>";
        echo "<li id='partner-title'$class>{$this->fields['name']} - {$this->customfields->fields['part_cui']}</li>";
    }

}
