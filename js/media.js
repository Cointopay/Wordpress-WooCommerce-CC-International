jQuery(document).ready(function ($) {
    var btnUplEoad = $('#woocommerce_cointopay_cc_upload-btn');
    var logolm = $('#woocommerce_cointopay_cc_logo');
    btnUpload.val('Upload Logo');
    displayLogo();
    btnUpload.click(function (e) {
        e.preventDefault();
        var image = wp.media({
            title: 'Upload Logo',
            multiple: false
        }).open().on('select', function (e) {
            // This will return the selected image from the Media Uploader, the result is an object
            var uploaded_image = image.state().get('selection').first();
            // We convert uploaded_image to a JSON object to make accessing it easier
            // Output to the console uploaded_image
            var image_url = uploaded_image.toJSON().url;
            // Let's assign the url value to the input field
            logoElm.val(image_url);
            displayLogo(true);
        });
    });
    function displayLogo(isReplace = false) {
        var logo = logoElm.val();
        if (isReplace) {
            $('#ctp-cc-logo').attr('src', logo);
        } else {
            var image = '<img id="ctp-cc-logo" style="width:100px;" src="' + logo + '" alt="logo">';
            logoElm.parent().append(image);
        }
    }
});