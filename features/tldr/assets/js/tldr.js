let old_tldr = null;

/**
 * get the raw markdown summary and display it in a textedit area instead of
 * the rendered HTML
 * param: optional text to append to the textedit area
 */
function make_tldr_editable() {
    let $tldr = $("#tldr");

    // if a textarea already, append to it
    if ($tldr.is('textarea')) {
        $tldr.val(function(index, value){
                return value;
            });

        // if not a textarea already, create one and replace the original div with it
    } else {
        $.get(
                  "/events/"+get_current_event_id()+"/tldr",
                  function(data) {
                      let textarea = $("<textarea></textarea>")
                          .attr({
                                  "id": "tldr",
                                  "name": "tldr",
                                  "class": "input-xxlarge editable",
                                  "rows": "10"
                              })
                          .val(data.tldr);
                      $tldr.replaceWith(textarea);
                      $("#tldr").on("save", tldr_save);
                  },
                  'json' // forces return to be json decoded
                  );
    }
}


/**
 * Depending on the current state either show the editable summary form or
 * save the markdown summary and render as HTML
 */
function tldr_save(e, event, history) {
    let old_tldr = '';
    let new_tldr = $("#tldr").val();

    let Diff = new diff_match_patch();

    let diff = Diff.diff_main(old_tldr, new_tldr);
    Diff.diff_cleanupSemantic(diff);
    diff = Diff.diff_prettyHtml(diff);
    history.tldr = diff;
    event.tldr = new_tldr;

    let html = $("<div></div>");
    html.attr("id", "tldr");
    html.attr("name", "tldr");
    html.attr("class", "input-xxlarge editable");
    html.attr("rows", "10");
    html.html(markdown.toHTML($("#tldr").val()));
    $("#tldr").remove();
    $("#tldr_wrapper").append(html);
    $("#tldr_undobutton").hide();
    $("#tldr").on("edit", make_tldr_editable);
}

/**
 * just abort editing and display the stored data as rendered HTML
 */
function tldr_undo_button() {
    $.get("/events/"+get_current_event_id()+"/tldr", function(data) {
            $('#tldr').val(data.tldr);
        });
}

$("#tldr").on("edit", make_tldr_editable);
$("#tldr_undobutton").on("click", tldr_undo_button);
$.get("/events/"+get_current_event_id()+"/tldr", function(data) {
        old_tldr = data.tldr;
    $("#tldr").html(markdown.toHTML(data.tldr));
});

