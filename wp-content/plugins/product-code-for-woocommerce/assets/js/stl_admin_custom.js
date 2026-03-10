jQuery(function () {
  
  jQuery(document).ready(function () {

    jQuery('#the-list').on('click', '.editinline', function () {

      /**
       * Extract metadata and put it as the value for the custom field form
       */
      // inlineEditPost.revert();

      var post_id = jQuery(this).closest('tr').attr('id');

      post_id = post_id.replace("post-", "");

      var current_item = jQuery('#pcfw_woocommerce_inline_' + post_id);

      var $cfd_inline_data = jQuery('#product_code_inline_' + post_id),
        $wc_inline_data = jQuery('#woocommerce_inline_' + post_id);
     
      jQuery('input[name="_product_code"]', '.inline-edit-row').val(current_item.find(".product_code_val").text());
      jQuery('input[name="_product_code_second"]', '.inline-edit-row').val(current_item.find(".product_code_2_val").text());
     

      /**
       * Only show custom field for appropriate types of products (simple)
       */
      var product_type = $wc_inline_data.find('.product_type').text();
     
      if (product_type == 'simple' || product_type == 'external') {
        jQuery('.product_code_1_label', '.inline-edit-row').show();
        jQuery('.product_code_2_label', '.inline-edit-row').show();
        jQuery('.product_code_clear', '.inline-edit-row').show();
      } else {
        jQuery('.product_code_1_label', '.inline-edit-row').hide();
        jQuery('.product_code_2_label', '.inline-edit-row').hide();
        jQuery('.product_code_clear', '.inline-edit-row').hide();
      }

    });

  });
});