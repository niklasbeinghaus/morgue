$(function () {
	$.get("/api/anniversary", function (d) {
		if (parseInt(d.anniversaries_today) > 0) {
			$(  $(".anniversary")[0]).append('<span class="badge badge-warning">'+ d.anniversaries_today +'</span>');
		}
	});
});
