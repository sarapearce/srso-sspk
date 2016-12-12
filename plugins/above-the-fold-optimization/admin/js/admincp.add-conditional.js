jQuery(function() {

    /**
     * Add conditional critical CSS
     */
    if (jQuery('#addcriticalcss-form').length > 0) {

        /**
         * Show form
         */
        jQuery('#addcriticalcss').on('click', function() {

            if (!jQuery('#addcriticalcss-form').is(':visible')) {

                if (!window.addccConditions) {
                    window.addccConditions = jQuery.parseJSON(jQuery('#addcc_condition_options').val());
                }
                
                var select = jQuery('#addcc_conditions');

                if (!select.data('conditions-loaded')) {

                    select.data('conditions-loaded',1);

                    /**
                     * Populate selectmenu
                     */
                    
                    select.empty();

                    select.append(jQuery('<option value=""></option>'));

                    var optgroupRegex = new RegExp('^<optgroup','i');
                    var optgroupEndRegex = new RegExp('^</optgroup','i');
                    var optionRegex = new RegExp('^<option','i');

                    var opt,optgroup;
                    for (var i = 0; i < window.addccConditions.length; i++) {
                        opt = window.addccConditions[i];

                        if (optgroupRegex.test(opt)) {
                            optgroup = jQuery(opt);
                        }
                        if (optgroupEndRegex.test(opt)) {
                            select.append(optgroup);
                            optgroup = false;
                        }
                        if (optionRegex.test(opt)) {
                            if (optgroup) {
                                optgroup.append(jQuery(opt));
                            } else {
                                select.append(jQuery(opt));
                            }
                        }
                    }

                    jQuery('#addcc_conditions').selectize({
                        persist         : true,
                        placeholder     : "Select one or more conditions...",
                        render: {
                            optgroup_header: function(item, escape) {
                                return '<div class="optgroup-header "><span class="'+item.class+'">&nbsp;</span>' + escape(item.label) + '</div>';
                            },
                            option: function(item, escape) {
                                return '<div>' +
                                    '<span class="title">' +
                                        '<span class="name">' + escape(item.text) + '</span>' +
                                    '</span>' +
                                '</div>';
                            },
                            item: function(item, escape) {
                                return '<div class="'+item.class+'" title="' + escape(item.text) + '">' +
                                    '<span class="name">' + escape(item.title||item.text) + '</span>' +
                                '</div>';
                            }
                        },
                        plugins         : ['remove_button']
                    });

                }
            }

            jQuery('#addcriticalcss-form').toggle();

        });

        /**
         * Save new conditional CSS
         */
        
        jQuery('#addcc_save').on('click', function() {

            var name = jQuery.trim(jQuery('#addcc_name').val());
            var conditions = jQuery('#addcc_conditions').val();

            if (name === '') {
                alert('Enter a name (admin reference)...');
                jQuery('#addcc_name').focus();
                return;
            }

            if (!/^[a-zA-Z0-9\-\_ ]+$/.test(name)) {
                alert('The name contains invalid characters.');
                jQuery('#addcc_name').focus();
                return;
            }

            if (!conditions || conditions === null) {
                alert('Select conditions...');
                return;
            }

            // create add form
            var form = jQuery('<form />');
            form.attr('method','post');
            form.attr('action',jQuery('#abtf_settings_form').data('addccss'));

            var input = jQuery('<input type="hidden" name="name" />');
            input.val(name);
            form.append(input);

            var input = jQuery('<input type="hidden" name="_wpnonce" />');
            input.val(jQuery('#_wpnonce').val());
            form.append(input);

            for (var i = 0; i < conditions.length; i++) {
                var input = jQuery('<input type="hidden" name="conditions[]" />');
                input.val(conditions[i]);
                form.append(input);
            }

            jQuery('body').append(form);

            jQuery(form).submit();

        });

    }

    
});