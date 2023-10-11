<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

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
     * @var PluginFieldsSuppliersuppliercustomfield
     */
    public $customfields = null;

    /*
     *
     *
     * @var array
     */
    public $hMarfa_fields = null;

    public static function getType(): string
    {
        return Supplier::getType();
    }

    public static function getTable($classname = null): string
    {
        return Supplier::getTable($classname);
    }

    public static function getFormURL($full = true): string
    {
        return parent::getFormURL($full);
    }

    public function additionalGetFromDbSteps($ID = null): void
    {
        $this->hMarfa_fields = self::gethMarfaFields($this->customfields->fields['hmarfa_code_field']);
    }

    public function getCustomFieldsModelName(): string
    {
        return 'PluginFieldsSuppliersuppliercustomfield';
    }

    public function hasCartridgeManagement(): bool
    {
        return !empty($this->customfields->fields['cm_field']);
    }

    public static function gethMarfaFields($cod_hmarfa): array
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
    public static function getFromTicketInput($input): self
    {
        $supplier_id = IserviceToolBox::getValueFromInput('_suppliers_id_assign', $input);

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
    public static function getFromMagicLink(): ?self
    {
        $magic_link           = IserviceToolBox::getInputVariable('id', null);
        $partner_customfields = new PluginFieldsSuppliersuppliercustomfield();
        if (!PluginIserviceDB::populateByQuery($partner_customfields, "WHERE magic_link_field = '$magic_link' LIMIT 1")) {
            return null;
        }

        $partner = new PluginIservicePartner();
        return $partner->getFromDB($partner_customfields->fields['items_id']) ? $partner : null;
    }

    public static function generateNewMagicLink($id): bool
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
                'magic_link_field' => base64_encode(mt_rand(10000, 99999) . str_pad($id, 5, '0', STR_PAD_LEFT) . mt_rand(10000, 99999))
            ]
        );
    }

    public function getMagicLink(): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . "/plugins/iservice/front/client.php?id=" . $this->customfields->fields['magic_link_field'];
    }

    public function getInvoiceInfo($invoice_info = 0): array
    {
        global $DB;
        $conditions = [
            "(codl = 'F' OR stare like 'V%') AND tip like 'TF%'",
            "codbenef = '{$this->customfields->fields['hmarfa_code_field']}'",
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

    public function getMailBody($type = '', $url_encoded = true): string
    {
        if (!empty($this->customfields->fields['magic_link_field'])) {
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
Cod fiscal: {$this->customfields->fields['uic_field']}

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
Cod fiscal: {$this->customfields->fields['uic_field']}
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

    public function displayPreferenceData(): void
    {
        if (Session::getLoginUserID()) {
            $class = '';
        } else {
            $class = " class='wide'";
        }

        echo "<script>$('#c_preference').addClass('no-user');</script>";
        echo "<li id='partner-title'$class>{$this->fields['name']} - {$this->customfields->fields['uic_field']}</li>";
    }

}
