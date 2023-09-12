/* exported clearDatabase */
function clearDatabase(url_base)
{
    let clearButton = $('#clear-button');

    if (clearButton.hasClass('disabled')) {
        return;
    }

    clearButton.addClass('disabled');
    $('.card').each(
        function () {
            adjustCardMassCheckbox($(this));
            $(this).find('.process-result').removeClass('fa-circle-check fa-circle-xmark fa-regular text-success text-danger');
        }
    );

    processNextItem('last', buildClearDBCallback, clearButton, url_base);
}

function buildClearDBCallback(itemType)
{
    return 'cleartable.php?itemType=' + itemType;
}

/* exported importFromOldIservice */
function importFromOldIservice(url_base)
{
    let importButton = $('#import-button');

    if (importButton.hasClass('disabled')) {
        return;
    }

    importButton.addClass('disabled');
    $('.card').each(
        function () {
            adjustCardMassCheckbox($(this));
            $(this).find('.process-result').removeClass('fa-circle-check fa-circle-xmark fa-regular text-success text-danger');
        }
    );

    processNextItem('first', buildImportCallback, importButton, url_base);
}

function buildImportCallback(itemType, startFromId = 0)
{
    return 'import.php?itemType=' + itemType
    + '&oldDBHost=' + $('#old-host').val()
    + '&oldDBName=' + $('#old-db').val()
    + '&oldDBUser=' + $('#old-user').val()
    + '&oldDBPassword=' + $('#old-pass').val()
    + '&startFromId=' + startFromId;
}

function processNextItem(firstOrLast, buildAjaxCallback, callerButton, url_base, startFromId = 0)
{
    let elementToProcess;
    if (firstOrLast === 'first') {
        elementToProcess = $('.list-group-item-action .form-check-input.to-process').first();
    } else {
        elementToProcess = $('.list-group-item-action .form-check-input.to-process').last();
    }

    if (elementToProcess.length === 0) {
        callerButton.removeClass('disabled');
        return;
    }

    let resultElement = elementToProcess.closest('.list-group-item-action').find('.process-result');
    let itemType      = elementToProcess.data('itemtype');
    let ajaxUrl       = url_base + '/ajax/' + buildAjaxCallback(itemType, startFromId);
    resultElement.addClass('fa-spinner fa-pulse');
    resultElement.attr('title', startFromId);

    $.get(
        ajaxUrl,
        function (data) {
            resultElement.removeClass('fa-spinner fa-pulse');

            try {
                let result = JSON.parse(data);
                let validResult = result.result !== undefined;

                if (validResult && result.result === 'OK') {

                    if (result.resultData.lastId !== undefined) {
                        processNextItem(firstOrLast, buildAjaxCallback, callerButton, url_base, result.resultData.lastId);
                        return;
                    }

                    resultElement.closest('.list-group-item-action').find('.form-check-input').removeClass('to-process');
                    resultElement.addClass('fa-circle-check fa-regular text-success ' + itemType);
                    resultElement.attr('title', 'Import successful');
                    processNextItem(firstOrLast, buildAjaxCallback, callerButton, url_base, 0);
                    return;
                }

                if (!validResult || result.result === 'ERROR') {
                    resultElement.addClass('fa-circle-xmark fa-regular text-danger ' + itemType);
                }

                resultElement.attr('title', 'See the errors in the log file.');
            } catch (e) {
                console.log(e);
            }
        }
    );

}

/* exported cardMassCheckboxClick */
function cardMassCheckboxClick(element)
{
    element.closest('.card').find('.list-group-item-action .form-check-input').prop('checked', element.is(':checked'));
    adjustCardMassCheckbox(element.closest('.card'));
}

function adjustCardMassCheckbox(card)
{
    let checkedCount = card.find('.list-group-item-action .form-check-input:checked').length;
    card.find('.card-mass-checkbox').prop('checked', checkedCount > 0);
    card.find('.form-check-input').each(
        function () {
            if ($(this).is(':checked')) {
                $(this).addClass('to-process');
            } else {
                $(this).removeClass('to-process');
            }
        }
    );
}
