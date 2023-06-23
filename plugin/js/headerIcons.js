$(document).ready(
    function () {
        moveIconsToHeader();
        handleClickHMarfaImport();
    }
);

function moveIconsToHeader()
{
    let dropDown       = $('li[title="Header Icons"]');
    let searchFormDiv  = $('form[role="search"]').parent();
    let headerIconsDiv = $('<div class="header-icons my-2 my-lg-0 ms-0 ms-lg-3 flex-grow-1 flex-lg-grow-0"></div>').insertAfter(searchFormDiv);
    dropDown.find('div.dropdown-menu-columns i').each(
        function () {
            $(this).appendTo(headerIconsDiv);
        }
    );

    dropDown.remove();
}

function handleClickHMarfaImport()
{
    $('i.hMarfaImport').click(
        function () {
            let regex  = /{([^}]+)}/g;
            let params = JSON.parse(regex.exec($(this).attr('class'))[0]);
            try {
                submitGetLink('/front/crontask.form.php', params);
                $(this).removeClass('text-danger text-warning');
            } catch (e) {
                console.log(e);
                $(this).addClass('text-danger');
            }
        }
    );
}
