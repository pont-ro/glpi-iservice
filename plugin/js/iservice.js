$(document).ready(
    function () {
        moveIconsToHeader();
        registerHMarfaImportClick();
    }
);

function moveIconsToHeader()
{
    console.log('moveIconsToHeader');
    let dropDown       = $('li[title="Header Icons"]');
    let searchFormDiv  = $('form[role="search"]').parent();
    let headerIconsDiv = $('<div class="header-icons my-2 my-lg-0 ms-0 ms-lg-3 flex-grow-1 flex-lg-grow-0"></div>').insertAfter(searchFormDiv);
    dropDown.find('div.dropdown-menu-columns a').each(
        function () {
            $(this).removeClass('dropdown-item');
            $(this).attr('data-params', $(this).attr('href').replace('/', ''));

            if (!$(this).find('i').hasClass('keepUrl')) {
                $(this).attr('href', 'javascript:void(0);');
            }

            $(this).find('span').remove();
            $(this).appendTo(headerIconsDiv);
        }
    );

    dropDown.remove();
}

function registerHMarfaImportClick()
{
    $('i.hMarfaImport').parent().click(
        function () {
            let regex  = /{([^}]+)}/g;
            let params = JSON.parse(regex.exec($(this).attr('data-params'))[0]);
            try {
                submitGetLink('/front/crontask.form.php', params);
                $(this).removeClass('text-danger text-warning');
            } catch (e) {
                $(this).attr('title', 'Error: ' + e.message);
                $(this).addClass('text-danger');
            }
        }
    );
}

// The code blow is imported from iService2. Needs refactoring.
function jq( selector )
{
    return selector.replace(/(:|\.|\[|\]|,|=|@)/g, "\\$1");
}

function setSelectField($wrapper_element, $value, $text)
{
    if ($wrapper_element.find("option[value='" + $value + "']").length) {
        $wrapper_element.val($value).trigger('change');
    } else {
        // Create a DOM Option and pre-select by default.
        var newOption = new Option($text, $value, true, true);
        // Append it to the select.
        $wrapper_element.append(newOption).trigger('change');
    }
}

function setGlpiDateField($wrapper_element, $value)
{
    $wrapper_element.find('input.flatpickr,input.flatpickr-input').val($value);
}

function setDateFilters($caller_element, $start_value, $end_value)
{
    $first_div  = $caller_element.nextAll('div.dropdown_wrapper:first');
    $second_div = $first_div.nextAll('div.dropdown_wrapper:first');
    setGlpiDateField($first_div, $start_value);
    setGlpiDateField($second_div, $end_value);
}

function setDailyAverage(url_base, printer_id, average, element_prefix, element_suffix)
{
    ajaxCall(
        url_base + "&id=" + printer_id + "&average=" + average,
        "Sigur doriți să setați valoarea pentru aparatul " + printer_id + " la " + average + "?",
        function (message) {
            let $link_element = $("." + element_prefix + "_link_" + printer_id);
            $link_element.text(message);
            if (message == average) {
                $link_element.css('color','');
            }

            $link_element.show();
            $("." + element_prefix + "_edit_" + printer_id).val(message);
            $("#" + element_prefix + "_span_" + printer_id + element_suffix).hide();
        }
    );
}

function manageItemViaAjax(url_base, item_id, sanitized_item_id, element_prefix, uid, confirm_message)
{
    var value = $("#" + element_prefix + "-edit-" + uid).val();
    if (confirm_message === undefined) {
        confirm_message = "Sigur doriți să schimbați valoarea la " + value + "?";
    }

    ajaxCall(
        url_base + '&id=' + item_id + "&value=" + value,
        confirm_message,
        function (message) {
            $link_element = $("." + element_prefix + "-link-" + sanitized_item_id);
            $link_element.text(message);
            if (message == value) {
                $link_element.css('color','');
            }

            $link_element.show();
            $("#" + element_prefix + "-span-" + uid).hide();
        }
    );
}

function detailSubmit(callingObject, requestArray, detailField, detailRow, detailKey, detailName)
{
    $("[name='" + requestArray + "[detail]'").val(detailField);
    $("[name='" + requestArray + "[detail_row]'").val(detailRow);
    $("[name='" + requestArray + "[detail_key]'").val(detailKey);
    var form = callingObject.closest("form");
    form.attr("action", "#view-query-" + detailName);
    form.submit();
}

function orderSubmit(callingObject, requestArray, orderBy, orderDir)
{
    $("[name='" + requestArray + "[order_by]'").val(orderBy);
    $("[name='" + requestArray + "[order_dir]'").val(orderDir);
    var form = callingObject.closest("form");
    form.attr("action", "#view-query-" + requestArray);
    form.submit();
}

function changeValByName(objectName, newValue)
{
    $element = $("[name='" + objectName + "'");
    if ($element.attr('type') === 'checkbox') {
        $element.prop('checked', newValue ? true : false);
    } else {
        $element.val(newValue);
    }
}

function ajaxCall(url, confirmMessage, onSuccess)
{
    if (confirmMessage === "" || confirm(confirmMessage)) {
        $.ajax(url).done(onSuccess).fail(
            function () {
                alert("Ajax call failed!");
            }
        );
    }
}

function ajaxCallWithFormData(url, formId, confirmMessage, onSuccess)
{
    if (confirmMessage === "" || confirm(confirmMessage)) {
        $.ajax({url: url, data: $("#" + formId).serializeArray()}).done(onSuccess).fail(
            function () {
                alert("Ajax call failed!");
            }
        );
    }
}

function openInNewTab(selector, attribute)
{
    if (attribute === undefined) {
        attribute = 'href'
    }

    $(selector).each(
        function () {
            window.open($(this).attr(attribute), '_blank');
        }
    );
}

function getEscapedId(id)
{
    return "#" + id.replace(/(:|\.|\[|\]|,|=|@)/g, "\\$1");
}

function addHrefParams(element, paramContainer)
{
    $(paramContainer).find(".toggler-checkbox").each(
        function () {
            if ($(this).is(':checked')) {
                $input = $(getEscapedId($(this).data('for')));
                $(element).attr("href", $(element).attr("href") + '&' + $input.data('param-name') + '=' + $input.val());
            }
        }
    );
}

function addRecurrentCheck(callback, milisec)
{
    if (milisec === undefined) {
        milisec = 500;
    }

    if (!callback()) {
        setTimeout(addRecurrentCheck, milisec, callback, milisec);
    }
}

let consumablesChanged = false;
let cartridgesChanged  = false;

jQuery(document).ready(
    function ($) {
        $.fn.bootstrapBtn = $.fn.button.noConflict();

        $(".non-admin .hide-for-non-admin").remove();

        const router_1week_cost   = 88;
        const router_2week_cost   = 120;
        const router_month_cost   = 176;
        const router_monthly_cost = 140;
        $('tr.calculates-total .switch-router-calculation-details').click(
            function () {
                if ($(this).val() == 'partial') {
                    $(this).val('lunar');
                    $(this).closest('tr').find('.switch-router-calculation-details-text').val('lunar');
                    $(this).closest('tr').find('.pret_unitar').val(router_monthly_cost);
                    $(this).closest('tr').find('.cantitate').val(1);
                    setGlpiDateField($(this).closest('tr').find('div.data_fact_until'), $(this).closest('tr').find('.data_fact_until_new').val());
                    $(this).closest('tr').find('.switch-router-calculation-part-details').prop('disabled', true);
                    $(this).closest('tr').find('.switch-router-calculation-part-details').addClass('disabled');
                } else {
                    $(this).val('partial');
                    $(this).closest('tr').find('.switch-router-calculation-details-text').val('partial');
                    setGlpiDateField($(this).closest('tr').find('div.data_fact_until'), $(this).closest('tr').find('.last_ticket_effective_date_field').val());
                    $(this).closest('tr').find('.switch-router-calculation-part-details').prop('disabled', false);
                    $(this).closest('tr').find('.switch-router-calculation-part-details').removeClass('disabled');
                }

                setDescription($(this));
                updateCalculationDetails($(this));
                $(this).closest('tr').find('.calculate-product-part').first().blur();
            }
        );

        $('tr.calculates-total .switch-router-calculation-part-details').click(
            function () {
                if ($(this).val() == 'lung') {
                    $(this).val('scurt');
                    $(this).closest('tr').find('.switch-router-calculation-part-details-text').val('scurt');
                } else {
                    $(this).val('lung');
                    $(this).closest('tr').find('.switch-router-calculation-part-details-text').val('lung');
                }

                updateCalculationDetails($(this));
                $(this).closest('tr').find('.calculate-product-part').first().blur();
            }
        );

        function updateCalculationDetails($element)
        {
            var data_fact_until = $element.closest('tr').find('div.data_fact_until').find('input.flatpickr,input.flatpickr-input').val();
            var data_exp        = $element.closest('tr').find('.invoice_expiry_date_field').val();
            var diff            = getDateDiff(data_exp, data_fact_until);
            var addition_cantitate;
            var addition_unit_price;
            var calculation_status      = $element.closest('tr').find('.switch-router-calculation-details').val();
            var calculation_part_status = $element.closest('tr').find('.switch-router-calculation-part-details').val();

            if (diff.dayDiff <= 0) {
                addition_cantitate  = 0;
                addition_unit_price = 0;
            } else if (diff.dayDiff < 8) {
                addition_cantitate  = 0.25;
                addition_unit_price = router_1week_cost;
            } else if (diff.dayDiff < 15) {
                addition_cantitate  = 0.5;
                addition_unit_price = router_2week_cost;
            } else {
                addition_cantitate = 1;
                if (calculation_status == 'partial' && calculation_part_status == 'scurt') {
                    addition_unit_price = router_month_cost;
                } else {
                    addition_unit_price = router_monthly_cost;
                }
            }

            if (diff.monthDiff > 0) {
                $element.closest('tr').find('.pret_unitar').val(router_monthly_cost);
                $element.closest('tr').find('.cantitate').val(diff.monthDiff + addition_cantitate);
                $element.closest('tr').find('.switch-router-calculation-part-details').val('lung');
                $element.closest('tr').find('.switch-router-calculation-part-details-text').val('lung');
            } else if (diff.monthDiff == 0 && calculation_status == 'partial') {
                if (calculation_part_status == 'lung') {
                    $element.closest('tr').find('.pret_unitar').val(router_monthly_cost);
                    $element.closest('tr').find('.cantitate').val(addition_cantitate);
                } else {
                    $element.closest('tr').find('.cantitate').val(1);
                    $element.closest('tr').find('.pret_unitar').val(addition_unit_price);
                }
            } else if (diff.monthDiff < 0) {
                $element.closest('tr').find('.pret_unitar').val(0);
                $element.closest('tr').find('.cantitate').val(0);
            }
        }

        function setDescription($element)
        {
            var data_exp        = $element.closest('tr').find('.invoice_expiry_date_field').val();
            var data_fact_until = $element.closest('tr').find('div.data_fact_until').find('input.flatpickr,input.flatpickr-input').val();

            var array                     = data_exp.split('-');
            var formatted_data_exp        = array[2] + '.' + array[1] + '.' + array[0];
            array                         = data_fact_until.split('-');
            var formatted_data_fact_until = array[2] + '.' + array[1] + '.' + array[0];

            var formatted_name = $element.closest('tr').find('.name').text();
            formatted_name     = formatted_name.replace("Router_", "");

            if ($element.closest('tr').find('.switch-router-calculation-details').val() == 'lunar') {
                $element.closest('tr').find('.description').val(formatted_name + " pentru perioada " + formatted_data_exp + " - " + formatted_data_fact_until);
            } else {
                $element.closest('tr').find('.description').val(formatted_name + " pentru perioada " + formatted_data_exp + " - " + formatted_data_fact_until + " (RETRAS)");
            }
        }

        function getDateDiff(startingDate, endingDate)
        {
            var startDate = new Date(startingDate);
            var endDate   = new Date(endingDate);

            if (startDate > endDate) {
                return { monthDiff: -1, dayDiff : -1};
            }

            var startYear   = startDate.getFullYear();
            var february    = (startYear % 4 === 0 && startYear % 100 !== 0) || startYear % 400 === 0 ? 29 : 28;
            var daysInMonth = [31, february, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

            var yearDiff  = endDate.getFullYear() - startYear;
            var monthDiff = endDate.getMonth() - startDate.getMonth();
            if (monthDiff < 0) {
                yearDiff--;
                monthDiff += 12;
            }

            var dayDiff = endDate.getDate() - startDate.getDate();
            if (dayDiff < 0) {
                if (monthDiff > 0) {
                    monthDiff--;
                } else {
                    yearDiff--;
                    monthDiff = 11;
                }

                dayDiff += daysInMonth[startDate.getMonth()];
            }

            if (yearDiff > 0) {
                monthDiff += 12 * yearDiff;
            }

            if (dayDiff == daysInMonth[startDate.getMonth()]) {
                monthDiff += 1;
                dayDiff    = 0;
            }

            return { monthDiff: monthDiff, dayDiff : dayDiff};

        }

        $('tr.calculates-total .calculate-product-part').blur(
            function () {
                let total = 1;
                $(this).closest('tr').find('.calculate-product-part').each(
                    function () {
                        total = total * parseFloat($(this).val());
                    }
                );
                $(this).closest('tr').find('.calculate-product').text(total);
                $(this).closest('tr').find('.calculate-product-hidden').val(total);
            }
        );

        $('tr.calculates-total .data_fact_until').find('input.flatpickr,input.flatpickr-input').change(
            function () {
                setDescription($(this));
                updateCalculationDetails($(this));
                $(this).closest('tr').find('.calculate-product-part').first().blur();
            }
        );

        $('tr.calculates-total').each(
            function () {
                var data_exp        = $(this).closest('tr').find('.invoice_expiry_date_field').val();
                var data_fact_until = $(this).closest('tr').find('div.data_fact_until').find('input.flatpickr,input.flatpickr-input').val();
                var diff            = getDateDiff(data_exp, data_fact_until);
                if ((new Date(data_fact_until) >= new Date(data_exp))  && (diff.monthDiff > 0 || diff.dayDiff > 22)) {
                    $(this).find('.switch-router-calculation-details').val('partial');
                } else {
                    $(this).find('.switch-router-calculation-details').val('lunar');
                }

                $(this).find('.switch-router-calculation-part-details').val('scurt');

                if (!$(this).find('.calculate-product-part').first().val()) {
                    $(this).find('.switch-router-calculation-details').first().click();
                }
            }
        );

        $('#iservice-body input[data-required-minimum].agressive, #iservice-body [data-required-minimum].agressive input').blur(
            function () {
                let required_minimum  = 0;
                let value_to_compare  = 0;
                let calling_component = null;
                if ($(this).hasClass('hasDatepicker')) {
                    calling_component = $(this).closest('[data-required-minimum]');
                    value_to_compare  = $(this).val() + ':00';
                    required_minimum  = calling_component.data("required-minimum");
                } else {
                    calling_component = $(this);
                    value_to_compare  = parseInt($(this).val());
                    required_minimum  = parseInt(calling_component.data("required-minimum"));
                }

                if ($(calling_component.data('ignore-min-value-if-not-set')) && $(calling_component.data('ignore-min-value-if-not-set')).val() === '') {
                    return;
                }

                if (value_to_compare < required_minimum) {
                    old_alert("Valoarea trebuie să fie minim " + required_minimum + "!");
                    field = $(this);
                    setTimeout(
                        function () {
                            field.focus();
                            field.val(required_minimum);
                        }, 100
                    );
                    return false;
                }
            }
        );

        var accepted_estimates = [];
        $('#iservice-body input[data-estimated]').blur(
            function () {
                if (accepted_estimates[this.name]) {
                    return;
                }

                estimated = parseInt($(this).attr("data-estimated"));
                if (estimated > 0 && Math.abs(parseInt($(this).val()) / estimated - 1) > 0.25) {
                    if (nativeConfirm("Valoarea estimată pentru " + this.name + " este " + estimated + ", ați introdus " + $(this).val() + ".\n")) {
                        accepted_estimates[this.name] = true;
                    } else {
                        field = $(this)
                        setTimeout(
                            function () {
                                field.focus();
                            }, 100
                        );
                    }
                }
            }
        );

        $('#iservice-body .iservice-form .required').blur(
            function () {
                if ($(this).val() === '') {
                    alert("Acest câmp este obligatoriu!");
                    $field = $(this);
                    setTimeout(
                        function () {
                            $field.focus();
                        }, 100
                    );
                    return false;
                }
            }
        );

        $('#iservice-body .iservice-form').submit(
            function (event) {
                if (consumablesChanged) {
                    if (!nativeConfirm('ATENȚIE! Nu ați salvat modificările pentru consumabile (cu butonul Actualizare). Sigur vreți să continuați?')) {
                        return false;
                    }
                }

                if (cartridgesChanged) {
                    if (!nativeConfirm('ATENȚIE! Nu ați salvat modificările pentru cartușe (cu butonul Actualizare). Sigur vreți să continuați?')) {
                        return false;
                    }
                }
            }
        )

        $('#iservice-body .iservice-form .submit').click(
            function (event) {
                var message       = '';
                var force_stop    = false;
                var allow_confirm = false;

                if ($(this).attr('data-confirm-first') !== undefined) {
                    if (!nativeConfirm($(this).attr('data-confirm-first'))) {
                        return false;
                    }
                }

                if ($(this).attr('data-required') !== undefined) {
                    $.each(
                        $(this).attr('data-required').split(','), function () {
                            $to_check = $("[name='" + this + "']");
                            if ($to_check.val() === undefined) {
                                return;
                            }

                            if (($to_check.val().trim() === '') || ($to_check.val().trim() === '0') || ($to_check.val().trim() === 0) || ($to_check.val().trim() === '0000-00-00')) {
                                message += $to_check.closest("tr").find("td").first().text() + " este obligatoriu!\n";
                            }
                        }
                    );
                }

                $(this).find(".required").each(
                    function () {
                        if ($(this).val() === '') {
                            message   += this.name + " este obligatoriu!\n";
                            force_stop = true;
                        }
                    }
                );

                $('#iservice-body input[data-required-minimum], #iservice-body [data-required-minimum] input').each(
                    function () {
                        let label             = '';
                        let required_minimum  = 0;
                        let value_to_compare  = 0;
                        let calling_component = null;
                        if ($(this).hasClass('hasDatepicker')) {
                            calling_component = $(this).closest('[data-required-minimum]');
                            value_to_compare  = $(this).val() + ':00';
                            required_minimum  = calling_component.data("required-minimum");
                            label             = calling_component.data('label');
                            if (label === undefined) {
                                label = calling_component.find("input[type='hidden']").attr('name');
                            }
                        } else {
                            calling_component = $(this);
                            value_to_compare  = parseInt($(this).val());
                            required_minimum  = parseInt(calling_component.data("required-minimum"));
                            label             = calling_component.data('label') === undefined ? this.name : calling_component.data('label')
                        }

                        if ($(calling_component.data('ignore-min-value-if-not-set')) && $(calling_component.data('ignore-min-value-if-not-set')).val() === '') {
                            return;
                        }

                        if (value_to_compare < required_minimum) {
                            message   += label + " trebuie să fie minim " + required_minimum + ", dar este " + value_to_compare + "!\n";
                            force_stop = true;
                        }
                    }
                );

                $('#iservice-body input[data-estimated]').each(
                    function () {
                        if (accepted_estimates[this.name]) {
                            return;
                        }

                        estimated = parseInt($(this).attr("data-estimated"));
                        if (Math.abs(parseInt($(this).val()) / estimated - 1) > 0.25) {
                            message      += "Valoarea estimată pentru " + this.name + " este " + estimated + ", ați introdus " + $(this).val() + ".";
                            message      += (force_stop ? "" : "Sigur vreti sa continuati?") + "\n";
                            allow_confirm = true;
                        }
                    }
                );

                if (message === '') {
                    message = $(this).data('confirm-message');
                    if (message) {
                        allow_confirm = true;
                    } else {
                        message = '';
                    }
                }

                if (message !== '') {
                    if (allow_confirm && !force_stop) {
                        return nativeConfirm(message);
                    }

                    alert(message);
                    return false;
                }
            }
        );

        var updateTextarea = function ($textarea, $changeCheckboxes) {
            $relatedCheckboxDiv = null;
            $textarea.closest('form').find('.textarea-checkboxes').each(
                function () {
                    if ($textarea.hasClass($(this).attr('data-target-textarea'))) {
                        $relatedCheckboxDiv = $(this);
                    }
                }
            );
            if ($relatedCheckboxDiv === null) {
                return;
            }

            $relatedCheckboxDiv.find('input.checkbox').each(
                function () {
                    if ($changeCheckboxes) {
                        this.checked = $textarea.val().indexOf($(this).attr('data-explanation')) > -1;
                    } else {
                        $textarea.val($textarea.val().replace($(this).attr('data-explanation'), ''));
                        if (this.checked) {
                            $textarea.val($textarea.val() + $(this).attr('data-explanation'));
                        }
                    }
                }
            );
        };

        $('#iservice-body .iservice-form [data-target-textarea] .checkbox').click(
            function () {
                updateTextarea($("." + $(this).closest('.textarea-checkboxes').attr('data-target-textarea')));
            }
        );

        $('#iservice-body .iservice-form .change-cartridge-description').change(
            function () {
                updateTextarea($(this), true);
            }
        );

        $('#iservice-body .iservice-form .change-cartridge-description').each(
            function () {
                $(this).change();
                // updateTextarea($(this), true);
            }
        );

        $("[data-for='_operator_reading']").click(
            function () {
                if ($("[name='_users_id_assign'] option[value='0']").length === 0) {
                    $("[name='_users_id_assign']").append(new Option('-----', 0, false, false));
                }

                if ($("[name='_users_id_assign'] option[value='27']").length === 0) {
                    $("[name='_users_id_assign']").append($(new Option('Cititor', 27, false, false)).attr('title', 'Cititor - cititor'));
                }

                if ($(this).prop('checked')) {
                    $("[name='_users_id_assign']").val('27');
                    $("[name='_users_id_assign']").trigger('change');
                } else {
                    $("[name='_users_id_assign']").val('0');
                    $("[name='_users_id_assign']").trigger('change');
                }
            }
        );

        $("#iservice-body .iservice-form.two-column .ticket-followups tr.tab_bg_3").each(
            function () {
                // $(this).find("td.center").css("width","10em");
                $(this).find("td.b").removeAttr("width");
            }
        );

        $("[name=generate_magic_link].new").click(
            function () {
                if (!confirm("ATENȚIE: Acest partener are deja un link magic!\nDacă generați un nou link magic, vechiul link nu va mai funcționa.\n\nSigur doriți să generați un link magic nou?")) {
                    return false;
                }
            }
        );

        $("li.collapsible label").click(
            function () {
                $(this).siblings("ul").toggle();
            }
        );

        var above_actions      = false;
        var above_actions_menu = false;
        $(".actions.collapsible").each(
            function () {
                $(this).wrap("<div class='actions-positioner'></div>");
                $("<div class='actions-menu'><img class='noprint view_action_button' src='/plugins/iservice/pics/legend.png'/></div>").insertBefore($(this).parent());
                $(this).hide();
            }
        ).hover(
            function () {
                above_actions = true;
                $(this).closest("td").find(".actions.collapsible").show();
            }, function () {
                if (!above_actions_menu) {
                    $(this).closest("td").find(".actions.collapsible").hide();
                }

                above_actions = false;
            }
        );

        $(".actions-menu img").click(
            function () {
                $(this).closest("td").find(".actions.collapsible").toggle();
            }
        ).hover(
            function () {
                above_actions_menu = true;
                $(this).closest("td").find(".actions.collapsible").show();
            }, function () {
                setTimeout(
                    function () {
                        if (!above_actions) {
                            $(this).closest("td").find(".actions.collapsible").hide();
                        }
                    }.bind(this), 500
                );
                above_actions_menu = false;
            }
        );

        $(".has-bootstrap-tooltip").each(
            function () {
                $(this).tooltip({html: true, trigger: 'click'});
            }
        );

        $(".checkbox-helper").change(
            function () {
                $(getEscapedId($(this).data("for"))).val($(this).is(':checked') ? 1 : 0);
            }
        );

        $(".compare-text-tooltip").each(
            function () {
                if ($(this).attr('title').indexOf($(this).text()) === -1) {
                    var color = 'green';
                    if ($(this).hasClass('color-red')) {
                        color = 'red';
                    }

                    if ($(this).hasClass('color-blue')) {
                        color = 'blue';
                    }

                    $(this).css("color", color);
                }
            }
        );

        $(".toggler-checkbox").change(
            function () {
                var checked = $(this).is(':checked');
                if (checked) {
                    $(getEscapedId($(this).data('for'))).show();
                    $(getEscapedId($(this).data('warning-not'))).hide();
                    $(getEscapedId($(this).data('warning-not'))).removeClass('visible');
                } else {
                    $(getEscapedId($(this).data('for'))).hide();
                    $(getEscapedId($(this).data('warning-not'))).show();
                    $(getEscapedId($(this).data('warning-not'))).addClass('visible');
                }

                $("[data-group='" + $(this).data('group') + "']").each(
                    function () {
                        if (!checked) {
                            if (!$(this).hasClass('force-disabled')) {
                                $(this).removeAttr('disabled');
                            }
                        } else {
                            if (!$(this).is(':checked')) {
                                $(this).attr('disabled', 'disabled');
                            }
                        }
                    }
                );
            }
        );

        $(".toggler-checkbox").change();

        $(".toggler").click(
            function () {
                $(getEscapedId($(this).data('for'))).toggle();
            }
        );

        $(".countdown").each(
            function () {
                let control        = $(this);
                let countdown_loop = setInterval(
                    function () {
                        let seconds = control.html();
                        if (seconds > 0) {
                            control.html(seconds - 1);
                        } else {
                            clearInterval(countdown_loop);
                        }
                    }, 1000
                );
            }
        );

        $(".char-count").keyup(
            function () {
                let textLength = $(this).val().length + $(this).data('add');
                let ccsClass   = textLength > 98 ? 'text-danger' : (textLength > 97 ? 'text-warning' : 'text-success');
                let control    = $("#" + $.escapeSelector($(this).attr('id')) + "-count");
                control.addClass(ccsClass);
                control.html(textLength > $(this).data('add') ? textLength : '');
            }
        );

        $(".char-count").each(
            function () {
                $(this).parent().attr('style', 'position: relative;');
                $(this).after('<span id="' + $(this).attr('id') + '-count" class="char-count-number"></span>');
                $(this).keyup();
            }
        );
    }
);
