jQuery(document).ready(function($) {
    // When the X button is clicked on the review notice
    $('.product_code_notice').on('click', '.notice-dismiss', function() {
        jQuery.post(ajaxurl, {
            action: 'product_code_dismiss_notice',
        });
    });

    // When the review link is clicked
    $('#pcfw_review_link').on('click', function() {
        jQuery.post(ajaxurl, {
            action: 'product_code_review_clicked',
        });
    });
});

// Code to add field title for 12/14 characters max
jQuery( document ).ready( function ($) {
   var first_max_chars = 12;
   var second_max_chars = 14;

   jQuery('#product_code_text').keydown( function(e){
       if (jQuery(this).val().length >= first_max_chars) { 
          jQuery(this).val(jQuery(this).val().substr(0, first_max_chars));
       }
   });

   jQuery('#product_code_text').keyup( function(e){
       if (jQuery(this).val().length >= first_max_chars) { 
           jQuery(this).val(jQuery(this).val().substr(0, first_max_chars));
       }
   });
   
   jQuery('#product_code_text_second').keydown( function(e){
       if (jQuery(this).val().length >= second_max_chars) { 
          jQuery(this).val(jQuery(this).val().substr(0, second_max_chars));
       }
   });

   jQuery('#product_code_text_second').keyup( function(e){
       if (jQuery(this).val().length >= second_max_chars) { 
           jQuery(this).val(jQuery(this).val().substr(0, second_max_chars));
       }
   });

   jQuery('#product_code_quik_edit_text').keydown( function(e){
       if (jQuery(this).val().length >= first_max_chars) { 
          jQuery(this).val(jQuery(this).val().substr(0, first_max_chars));
       }
   });

   jQuery('#product_code_quik_edit_text').keyup( function(e){
       if (jQuery(this).val().length >= first_max_chars) { 
           jQuery(this).val(jQuery(this).val().substr(0, first_max_chars));
       }
   });
   
   jQuery('#product_code_quik_edit_text_second').keydown( function(e){
       if (jQuery(this).val().length >= second_max_chars) { 
          jQuery(this).val(jQuery(this).val().substr(0, second_max_chars));
       }
   });

   jQuery('#product_code_quik_edit_text_second').keyup( function(e){
       if (jQuery(this).val().length >= second_max_chars) { 
           jQuery(this).val(jQuery(this).val().substr(0, second_max_chars));
       }
   });

   // Delete data on uninstall confirmation
   jQuery('#pcfw_delete_data_on_uninstall').on('change', function() {
       if (jQuery(this).is(':checked')) {
           var confirmed = confirm('⚠️ WARNING: Are you sure?\n\nEnabling this option means ALL product code data will be PERMANENTLY DELETED if you uninstall this plugin.\n\nThis action cannot be undone!\n\nClick OK to enable, or Cancel to keep your data safe.');
           if (!confirmed) {
               jQuery(this).prop('checked', false);
           }
       }
   });

   // Support button click handler - sends notification email
   if (typeof pcfw !== 'undefined') {
       jQuery('.pcfw-support-btn').on('click', function() {
           jQuery.post(pcfw.ajaxurl, {
               action: 'pcfw_support_notification',
               nonce: pcfw.nonce
           });
       });
   }

});
