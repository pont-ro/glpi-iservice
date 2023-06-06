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
            $(this).find('.import-result').removeClass('fa-circle-check fa-circle-xmark fa-regular text-success text-danger');
        }
    );

    importFirstItemFromOldIservice(url_base);
}

function importFirstItemFromOldIservice(url_base)
{
    let elementToProcess = $('.list-group-item-action .form-check-input.to-process').first();

    if (elementToProcess.length === 0) {
        $('#import-button').removeClass('disabled');
        return;
    }

    let resultElement = elementToProcess.closest('.list-group-item-action').find('.import-result');
    let itemType      = elementToProcess.data('itemtype');
    let ajaxUrl       = url_base + '/ajax/import.php?itemtype=' + itemType;

    resultElement.addClass('fa-spinner fa-pulse');

    $.get(
        ajaxUrl,
        function (data) {
            resultElement.removeClass('fa-spinner fa-pulse');
            resultElement.closest('.list-group-item-action').find('.form-check-input').removeClass('to-process');

            if (data === 'OK') {
                resultElement.addClass('fa-circle-check fa-regular text-success ' + itemType);
                importFirstItemFromOldIservice(url_base);
            } else {
                resultElement.addClass('fa-circle-xmark fa-regular text-danger ' + itemType);
                $('#import-button').removeClass('disabled');
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
