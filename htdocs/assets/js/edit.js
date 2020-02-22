const EDIT_UNLOCKED = 0;
const EDIT_LOCKED = 1;
const EDIT_CLOSED = 2;
// update the lock every 60 seconds
const EDIT_TIME = 10000;
let edit_lock;


$("#add-status").on("click", show_status_fields);
$("#clear-status").on("click", remove_status_fields);
$("#severity-select").on("save", update_severity_for_event);
$("#problem_type").on("save", update_problem_type_for_event);
$("#impact_type").on("save", update_impact_type_for_event);
$("#incident_cause").on("save", update_incident_cause_for_event);
$("#subsystem").on("save", update_subsystem_for_event);
$("#owner_team").on("save", update_owner_team_for_event);
$("#event-detect-input-date").on("save", update_detectdate_for_event);
$("#event-detect-input-time").on("save", update_detecttime_for_event);
$("#event-status-input-time").on("save", update_statusdatetime_for_event);
$("#event-end-input-date").on("save", update_enddate_for_event);
$("#event-end-input-time").on("save", update_endtime_for_event);
$("#event-start-input-date").on("save", update_startdate_for_event);
$("#event-start-input-time").on("save", update_starttime_for_event);
$("#eventtitle").on("save", update_title_for_event);
$("#gcal").on("save", update_gcal_for_event);
$("#contact").on("save", update_contact_for_event);

$('.datepicker')
    .datepicker({
        format: 'mm/dd/yyyy'
    });

$('.timeentry')
    .timeEntry({
        spinnerImage: ''
    });

$('#delete-initial').click(function (ev) {
    ev.preventDefault();
    $(this).hide();
    $("#delete_button_confirmation_container").show();
});

$("#delete-yes").click(function (ev) {
    ev.preventDefault();
    delete_event(function (data, textStatus, jqXHR) {
        if (jqXHR.status === 204) {
            window.location = '/';
        }
    })
});

$("#delete-no").click(function (ev) {
    ev.preventDefault();
    $('#delete-initial').show();
    $("#delete_button_confirmation_container").hide();
});

// Enter key blurs input elements
$(":input").keyup(function (e) {
    if (e.keyCode == 13) {
        e.target.blur();
    }
});

function update_lock() {
    return $.getJSON("/events/" + get_current_event_id() + "/lock", function (data) {
    });
}

function make_editable() {
    $.getJSON("/events/" + get_current_event_id() + "/lock", function (data) {
        let edit_div = $("<div></div>");
        if (data.status === EDIT_UNLOCKED) {
            $(".editable").removeAttr("disabled");
            $(".editable_hidden").show();
            $(".editable").trigger("edit");

            edit_div.attr({
                "id": "edit_div",
                "class": "alert alert-success",
                "role": "alert"
            });
            edit_div.html("Save Changes");
            edit_lock = setInterval(update_lock, EDIT_TIME);
        } else if (data.status === EDIT_LOCKED) {
            edit_div.attr({
                "id": "edit_div",
                "class": "alert alert-danger",
                "role": "alert"
            });
            edit_div.html("<strong>" + data.modifier + "</strong> is currently editing this page.");
            $('#edit_status').off('click');
            $('#edit_status').on('click', function () {
                location.reload();
            });
        } else {
            edit_div.attr({
                "id": "edit_div",
                "class": "alert alert-warning",
                "role": "alert"
            });
            edit_div.html("The edit period for this event has expired");
            $('#edit_status').off('click');
        }
        $("#edit_div").replaceWith(edit_div);
    });
}

function save_page() {
    clearInterval(edit_lock);
    let hist = {};
    let event = {};
    hist.action = 'edit';
    $(".editable").trigger("save", [event, hist]);
    $(".editable_hidden").hide();
    $("input.editable").prop("disabled", true);
    $("select.editable").prop("disabled", true);

    update_history(hist);
    update_event(event);

    let edit_div = $("<div></div>");
    edit_div.attr({
        "id": "edit_div",
        "class": "alert alert-info",
        "role": "alert"
    });
    edit_div.html("Click here to make changes");
    $("#edit_div").replaceWith(edit_div);
}

$("#edit_status").click(function (ev) {
    let in_edit = ($('#edit_div').html() === "Save Changes");
    if (in_edit) {
        save_page();
    } else {
        make_editable();
    }
});

let edit_window = $(window);
let edit_status = $('#edit_status');
let edit_top = edit_status.offset().top;

edit_window.scroll(function () {
    edit_status.toggleClass('sticky', edit_window.scrollTop() > edit_top);
});
