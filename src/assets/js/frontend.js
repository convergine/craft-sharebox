$(document).ready(function () {
    updateFilesTable();
    //document.folder_id = 0;
    document.folder_parent_id = null;

    $("#conv_folder_up").on('click', function () {
        document.folder_id = document.folder_parent_id;
        getFilesTable();
    })

    /*$("#conv-login-button").on('click', function () {
        if ('undefined' !== typeof grecaptcha) {
            grecaptcha.ready(function () {
                grecaptcha.execute(conv_recaptcha_site, {action: 'submit'}).then(function (token) {
                    $("#token").val(token);
                    $("#login-form").submit()
                })
            })
        } else {
            $("#conv-login-form").submit()
        }

    })*/
    $("#conv-login-form").on('submit',function (){
        if ('undefined' !== typeof grecaptcha) {
            $("#conv-login-form").off('submit')
            grecaptcha.ready(function () {
                grecaptcha.execute(conv_recaptcha_site, {action: 'submit'}).then(function (token) {
                    $("#token").val(token);
                    $("#conv-login-form").submit()
                })
            })
            return false;
        }
        return true;
    })
});

function updateFilesTable() {

    var table = $('#conv_files_table').DataTable({
        //dom: "<'row'<'col-sm-12 col-md-6'il><'col-sm-12 col-md-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'B><'col-sm-12 col-md-7'p>>",
        lengthChange: false,
        stateSave: false,
        "order": [],
        "columnDefs": [{
            "targets": 'no-sort',
            "orderable": false,
            "order": []
        }]
    });

    $(".open_folder").on('click', function () {
        document.folder_id = $(this).data('id');
        getFilesTable();
    })
}

function getFilesTable() {
    $("#conv_files_container").addClass('conv_loading');
    $.ajax({
        url: "/actions/convergine-sharebox/frontend/get-files-table",
        data: {'folder_id': document.folder_id, "CRAFT_CSRF_TOKEN": $("input[name='CRAFT_CSRF_TOKEN']").val()},
        type: "POST",
        dataType: "json",
        success: function (data) {
            if (data.res) {
                $("#conv_files_table_cont").html(data.table);
                document.folder_parent_id = data.parent_id;
                $("#conv_files_breadcrumb").html(data.folder_path)
                if (data.parent_id == '-1') {
                    $("#conv_folder_up").hide()
                } else {
                    $("#conv_folder_up").show()
                }
                updateFilesTable();
                $("#conv_files_container").removeClass('conv_loading');
            } else {
                window.location.reload();
            }

        },
        error: function (data) {
            $("#conv_files_container").removeClass('conv_loading');
        }
    });
}