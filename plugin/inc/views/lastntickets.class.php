<?php

// Imported from iService2, needs refactoring. Original file: "Last_n_Tickets.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Views\View;
use \CommonITILActor;

class LastNTickets extends View
{
    public static $rightname = 'plugin_iservice_view_tickets';

    public static $icon = 'ti ti-ticket';

    public static function getName(): string
    {
        return _t('Last n tickets');
    }

    const TYPE_FOR_PRINTER = 'for_printer';
    const TYPE_PLATI       = 'plati';

    protected $n    = 0;
    protected $type = null;

    public function customize($params = [])
    {
        parent::customize($params);
        $this->type = $params['type'] ?? null;
        switch ($this->type) {
        case self::TYPE_FOR_PRINTER:
            if (!isset($params['printer_id'])) {
                $this->type = null;
                return false;
            }

            $this->printer_id = $params['printer_id'];
            break;
        case self::TYPE_PLATI:
            $this->supplier_id = $params['supplier_id'] ?? null;
            break;
        default:
            $this->type = null;
            return false;
        }

        $this->n = $params['n'] ?? 5;
        return true;
    }

    public function display($readonly = false, $export = false, $detail = 0, $generate_form = true): void
    {
        if (empty($this->type)) {
            die("View must be customized first!");
        }

        parent::display($readonly, $export, $detail, $generate_form);
    }

    protected function getSettings(): array
    {
        if (empty($this->type)) {
            return [];
        }

        $select_fields = "t.id ticket_id, t.date date_open";
        $joins         = "";
        $conditions    = "";
        switch ($this->type) {
        case self::TYPE_FOR_PRINTER:
            $select_fields .= "
                    , t.total2_black_field
                    , t.total2_color_field
                    , p.id printer_id
                    , p.name printer_name
                    , p.serial printer_serial
                    , l.completename printer_location
                    , GROUP_CONCAT(tf.content SEPARATOR '<br>') ticket_followups";
            $joins         .= " LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer'";
            $joins         .= " LEFT JOIN glpi_printers p ON p.id = it.items_id";
            $joins         .= " LEFT JOIN glpi_locations l ON l.id = p.locations_id";
            $joins         .= " LEFT JOIN glpi_itilfollowups tf ON tf.items_id = t.id and tf.itemtype = 'Ticket'" . (!in_array($_SESSION["glpiactiveprofile"]["name"], ['tehnician', 'admin', 'super-admin']) ? ' AND NOT tf.is_private = 1' : '');
            $conditions    .= " AND p.id = $this->printer_id";
            break;
        case self::TYPE_PLATI:
            $select_fields .= ", au.name ticket_assigned_to, t.name, t.content";
            $joins         .= " LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id";
            $joins         .= " LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = t.id and tu.type = " . CommonITILActor::ASSIGN;
            $joins         .= " LEFT JOIN glpi_users au ON au.id = tu.users_id";
            $conditions    .= " AND c.name = 'Plati'";
            if (!empty($this->supplier_id)) {
                $joins      .= " LEFT JOIN glpi_suppliers_tickets st ON st.tickets_id = t.id AND st.type = " . CommonITILActor::ASSIGN;
                $conditions .= " AND st.suppliers_id = $this->supplier_id";
            }
            break;
        default:
            return [];
        }

        return [
            'sub_view' => true,
            'query' => "
						SELECT $select_fields
						FROM glpi_plugin_iservice_tickets t $joins
						WHERE t.is_deleted = 0 $conditions
						GROUP BY t.id
						",
            'default_limit' => $this->n,
            'columns' => [
                'ticket_id' => [
                    'title' => 'Număr',
                    'align' => 'center',
                ],
                'date_open' => [
                    'title' => 'Data tichet',
                    'default_sort' => 'DESC',
                ],
                'printer_name' => [
                    'title' => 'Nume aparat',
                    'format' => '%s ([printer_location])',
                    'visible' => $this->type === self::TYPE_FOR_PRINTER,
                ],
                'printer_serial' => [
                    'title' => 'Serie aparat',
                    'style' => 'white-space: nowrap;',
                    'visible' => $this->type === self::TYPE_FOR_PRINTER,
                ],
                'total2_black_field' => [
                    'title' => 'Contor alb-negru',
                    'visible' => $this->type === self::TYPE_FOR_PRINTER,
                ],
                'total2_color_field' => [
                    'title' => 'Contor color',
                    'visible' => $this->type === self::TYPE_FOR_PRINTER,
                ],
                'ticket_followups' => [
                    'title' => 'Descriere followup-uri',
                    'visible' => $this->type === self::TYPE_FOR_PRINTER,
                ],
                'ticket_assigned_to' => [
                    'title' => 'Repartizat către',
                    'visible' => $this->type === self::TYPE_PLATI,
                ],
                'name' => [
                    'title' => 'Titlu',
                    'visible' => $this->type === self::TYPE_PLATI,
                ],
                'content' => [
                    'title' => 'Descriere',
                    'visible' => $this->type === self::TYPE_PLATI,
                ],
            ],
        ];
    }

}
