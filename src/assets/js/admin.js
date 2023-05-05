$(document).ready(function () {

    document.folder_id = 0;
    document.folder_parent_id = null;

    updateFilesTable()

    if ($("div#file_upload").length) {

        $("div#file_upload").dropzone({
            url: '/actions/convergine-sharebox/files/upload-file',
            addRemoveLinks: true,
            init: function () {
                this.on("addedfile", file => {
                    console.log("A file has been added");
                });
                this.on("sending", function(file, xhr, formData) {
                    formData.append("CRAFT_CSRF_TOKEN", $("input[name='CRAFT_CSRF_TOKEN']").val());
                    formData.append('folder_id',document.folder_id);
                });
            },
            success(file, serverResponse) {
                file.file_id = serverResponse.file_id
                if(!serverResponse.res){
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: serverResponse.msg
                    })
                }
            },
            queuecomplete(){
                $('#file_upload .dz-preview').fadeOut();
                getFilesTable()
            }
        });
    }

    $("#folder_up").on('click',function (){
        document.folder_id = document.folder_parent_id;
        getFilesTable();
    })

    $("#analytics_btn").on('click',function (){
        const form = $(this).parents('form');
        $("#folder_file_cont").addClass('loading');
        $.ajax({
            url: "/actions/convergine-sharebox/analytics/get-table",
            data: form.serializeArray(),
            type: "POST",
            dataType: "json",
            success: function (data) {
                if(data.res){
                    $("#analytics_table_cont").html(data.table);
                }else{
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.msg
                    })
                }

                $("#folder_file_cont").removeClass('loading');
            },
            error(data){
                $("#folder_file_cont").removeClass('loading');
            }
        });
    });

    $(".conv_remove_btn").on('click','.dataTable',function (){
        let $el = $(this);
        Swal.fire({
            title: 'Do you want to delete selected record?',

            showCancelButton: true,
            confirmButtonText: 'Remove',
        }).then((result) => {
            /* Read more about isConfirmed, isDenied below */
            if (result.isConfirmed) {
                $el.parents('form').submit()
            }
        })
        return false;
    })

});

function updateFilesTable(){

    var table = $('#files').DataTable({

        lengthChange: false,

        stateSave: false,
        "order": []
    });

    $(".conv_open_folder").on('click',function (){
        document.folder_id = $(this).data('id');
        getFilesTable();
    })
    $(".dataTable").on('click','.conv_remove_folder_btn',function (){
        deleteFolder($(this));
    })
    $(".dataTable").on('click','.conv_remove_file',function (){
        deleteFile($(this));
    })
    $(".dataTable").on('click','.conv_url_btn',function (){
        copyToClipboard($(this).data('url'))
        Swal.fire({
            icon: 'success',
            title: 'URL Copied'
        })
    })
    $(".conv_move_folder_btn").on('click',function (){
        moveFolder($(this));
    })
    $(".conv_move_file_btn").on('click',function (){
        moveFile($(this));
    })
}

function copyToClipboard(text) {
    var sampleTextarea = document.createElement("textarea");
    document.body.appendChild(sampleTextarea);
    sampleTextarea.value = text; //save main text in it
    sampleTextarea.select(); //select textarea contenrs
    document.execCommand("copy");
    document.body.removeChild(sampleTextarea);
}

function getFilesTable(){
    $("#folder_file_cont").addClass('loading');
    $.ajax({
        url: "/actions/convergine-sharebox/files/get-files-table",
        data: {'folder_id':document.folder_id,"CRAFT_CSRF_TOKEN": $("input[name='CRAFT_CSRF_TOKEN']").val()},
        type: "POST",
        dataType: "json",
        success: function (data) {
            $("#files_table_cont").html(data.table);
            document.folder_parent_id = data.parent_id;
            $("#folder_name").html(data.folder_path)
            if(data.parent_id == '-1'){
                $("#folder_up").hide()
            }else{
                $("#folder_up").show()
            }
            updateFilesTable();
            $("#folder_file_cont").removeClass('loading');
        },
        error(data){
            alert('Unterminated error. Please, repeat')
            $("#folder_file_cont").removeClass('loading');
        }
    });
}

async function  addFolder(){
    const { value: folderName } = await Swal.fire({
        title: 'Enter folder name',
        input: 'text',
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) {
                return 'You need to write something!'
            }
        }
    })

    if (folderName) {
        $("#folder_file_cont").addClass('loading');
        $.ajax({
            url: "/actions/convergine-sharebox/files/create-folder",
            data: {'folder_id':document.folder_id,"CRAFT_CSRF_TOKEN": $("input[name='CRAFT_CSRF_TOKEN']").val(),folder_name:folderName},
            type: "POST",
            dataType: "json",
            success: function (data) {
                if(data.res){
                    Swal.fire({
                        icon: 'success',
                        title: data.msg
                    })
                    $("#files_table_cont").html(data.table);
                    updateFilesTable();
                }else{
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.msg
                    })
                }

                $("#folder_file_cont").removeClass('loading');
            },
            error(data){

            }
        });
    }
}

async function editFolder(id,name){
    const { value: folderName } = await Swal.fire({
        title: 'Enter folder name',
        input: 'text',
        inputValue: name,
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) {
                return 'You need to write something!'
            }
        }
    })

    if (folderName) {
        $("#folder_file_cont").addClass('loading');
        $.ajax({
            url: "/actions/convergine-sharebox/files/edit-folder",
            data: {
                'parent_id':document.folder_id,
                "CRAFT_CSRF_TOKEN": $("input[name='CRAFT_CSRF_TOKEN']").val(),
                folder_name:folderName,
                folder_id:id
            },
            type: "POST",
            dataType: "json",
            success: function (data) {
                if(data.res){
                    Swal.fire({
                        icon: 'success',
                        title: data.msg
                    })
                    $("#files_table_cont").html(data.table);
                    updateFilesTable();
                }else{
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.msg
                    })
                }

                $("#folder_file_cont").removeClass('loading');
            },
            error(data){

            }
        });
    }
}

function moveFolder($button){
    const form = $button.parents('form');
    const url = form.attr('action');
    $("#folder_file_cont").addClass('loading');
    $.ajax({
        url: url,
        data: form.serializeArray(),
        type: "POST",
        dataType: "json",
        success: function (data) {
            getFoldersPopup("/actions/convergine-sharebox/files/move-folder",data,'')
            $("#folder_file_cont").removeClass('loading');
        },
        error(data){

        }
    });
}

async function getFoldersPopup(action,data,error,val){
    let html = '';
    let value = undefined === val?'':val;
    if(error){
        html+="<div style='background-color: red;color:#fff;padding: 10px'>" + error + "</div>";

    }
    html+='Move folder <b>'+data.folder_path+'</b> to ';
    const { value: targetFolder } = await Swal.fire({
        title: 'Select target folder',
        html: html,
        input: 'select',
        inputValue: value,
        inputOptions:data.folders,
        inputPlaceholder: 'Select target folder',
        showCancelButton: true,
        inputValidator: (value) => {
            if (!value) {
                return 'Please, Select target folder!'
            }
        }
    })

    if (targetFolder) {

        $("#folder_file_cont").addClass('loading');
        let targetFolder_id = targetFolder.replace('ID:','');
        $.ajax({
            url: action,
            data: {'from':data.move_folder??data.move_file,'to':targetFolder_id,"CRAFT_CSRF_TOKEN": $("input[name='CRAFT_CSRF_TOKEN']").val()},
            type: "POST",
            dataType: "json",
            success: function (response) {
                if(response.res){
                    document.folder_id = targetFolder_id;
                    getFilesTable();
                }else{
                    getFoldersPopup(action,data,response.msg,targetFolder)
                }

                $("#folder_file_cont").removeClass('loading');
            },
            error(data){
                alert('Unterminated error')
                $("#folder_file_cont").removeClass('loading');
            }
        });
    }

}

function moveFile($button){
    const form = $button.parents('form');
    const url = form.attr('action');
    $("#folder_file_cont").addClass('loading');
    $.ajax({
        url: url,
        data: form.serializeArray(),
        type: "POST",
        dataType: "json",
        success: function (data) {
            getFoldersPopup("/actions/convergine-sharebox/files/move-file",data,'')
            $("#folder_file_cont").removeClass('loading');
        },
        error(data){

        }
    });
}


async function deleteFolder($button){
    Swal.fire({
        title: 'Are you sure you would like to delete the folder?',
        text:'All files and subfolders will be removed',
        showCancelButton: true,
        confirmButtonText: 'Submit',
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            const form = $button.parents('form');
            const url = form.attr('action');
            $("#folder_file_cont").addClass('loading');
            $.ajax({
                url: url,
                data: form.serializeArray(),
                type: "POST",
                dataType: "json",
                success: function (data) {
                    if(data.res){
                        Swal.fire({
                            icon: 'success',
                            title: data.msg
                        })
                        $("#files_table_cont").html(data.table);
                        updateFilesTable();
                    }else{
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: data.msg
                        })
                    }

                    $("#folder_file_cont").removeClass('loading');
                },
                error(data){

                }
            });
        }
    })
}

async  function deleteFile($button){
    Swal.fire({
        title: 'Are you sure you would like to delete the file?',
        showCancelButton: true,
        confirmButtonText: 'Submit',
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            const form = $button.parents('form');
            const url = form.attr('action');
            $("#folder_file_cont").addClass('loading');
            $.ajax({
                url: url,
                data: form.serializeArray(),
                type: "POST",
                dataType: "json",
                success: function (data) {
                    if(data.res){
                        Swal.fire({
                            icon: 'success',
                            title: data.msg
                        })
                        $("#files_table_cont").html(data.table);
                        updateFilesTable();
                    }else{
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: data.msg
                        })
                    }

                    $("#folder_file_cont").removeClass('loading');
                },
                error(data){

                }
            });
        }
    })
}