if (typeof oak == "undefined" || !oak) {
    var oak = {};
}

oak.assigment_to_order = {
    init: function() {
        $(document).on('submit', '.assigment-to-order-form', this.send_to_order);
    },
    send_to_order: function() {
        var form = $(this);
		$(form).css('opacity', '0.3');
		$.post(
			$(form).attr('action'),
			$(form).serialize(),
            function(answer) {
				$(form).css('opacity', '1');
                if(answer.order_view_location) {
                    document.location = answer.order_view_location;
                }
            },
            "json"
        );

        return false;
    }
};

oak.assigment_to_order.init();
