/*
 * Plugin Name: Advanced Sidebox for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * intercept the submit button, submit
 * with ajax, and eval return scripts
 */

!function($) {
	function init() {
		$("#modalSubmit").click(submitForm);
	}

	function submitForm (e) {
		e.preventDefault();

		$.ajax({
			type: "POST",
			url: $("#modal_form").attr("action") + "&ajax=1",
			data: $("#modal_form").serialize(),
			success: function(data) {
				$(data).filter("script").each(function(e) {
					eval($(this).text());
				});
				$.modal.close();
			},
			error: function(jqXHR, textStatus, errorThrown) {
				alert(textStatus +
					"\n\n" +
					errorThrown);
			},
		});
	}

	$(init);
}(jQuery);
