$(document).ready(
    function () {
        moveIconsToHeader();
        registerHMarfaImportClick();
    }
);

function moveIconsToHeader()
{
    let dropDown       = $('li[title="Header Icons"]');
    let searchFormDiv  = $('form[role="search"]').parent();
    let headerIconsDiv = $('<div class="header-icons my-2 my-lg-0 ms-0 ms-lg-3 flex-grow-1 flex-lg-grow-0"></div>').insertAfter(searchFormDiv);
    dropDown.find('div.dropdown-menu-columns a').each(
        function () {
            $(this).removeClass('dropdown-item');
            $(this).attr('data-params', $(this).attr('href').replace('/', ''));
            $(this).attr('href', 'javascript:void(0);');
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
