jQuery(function() {

    /**
     * Advanced Critical CSS editor
     */
    var advancedEditor = (jQuery('#abtfcss').length > 0 && parseInt(jQuery('#abtfcss').data('advanced')) === 1) ? true : false;

    // CodeMirror instances
    var advancedEditors = {};

    if (advancedEditor) {

        // load editor
        var loadEditor = function(editor_id) {

            if (jQuery('#ccss_editor_'+editor_id+' .abtfcss').length === 0) {
                return;
            }

            // codemirror
            advancedEditors[editor_id] = CodeMirror.fromTextArea(
                jQuery('#ccss_editor_'+editor_id+' .abtfcss')[0], {
                lineWrapping: true,
                lineNumbers: true,
                gutters: ["CodeMirror-lint-markers"],
                lint: true
            });
            advancedEditors[editor_id].on('change', function() {
                window.inputChange = true;
            });

            jQuery('#ccss_editor_'+editor_id).closest('.menu-item').removeClass('menu-item-edit-inactive').addClass('menu-item-edit-active');

            resizeEditors();
        }

        // unload editor
        var unloadEditor = function(editor_id) {
            if (advancedEditors[editor_id]) {
                advancedEditors[editor_id].save();
                advancedEditors[editor_id].toTextArea();
                advancedEditors[editor_id] = false;

                jQuery('#ccss_editor_'+editor_id).closest('.menu-item').addClass('menu-item-edit-inactive').removeClass('menu-item-edit-active');

            }
        }

        // resize editor
        var resizeEditors = function() {
            var d = jQuery('.CodeMirror').closest('.inside').outerWidth();
            var w = (d - 26);
            jQuery('.CodeMirror').css({width: w + 'px'});
        }


        /**
         * Resize editors on window resize
         */
        jQuery( window ).resize(function() {

            resizeEditors();

            for (editor_id in advancedEditors) {
                if (advancedEditors.hasOwnProperty(editor_id)) {
                    continue;
                }
                if (advancedEditors[editor_id]) {
                    advancedEditors[editor_id].refresh();
                }
            }
        });
    }

    // load condition selectize for editor
    var loadConditionSelect = function(editor_id) {
        if (!window.addccConditions) {
            window.addccConditions = jQuery.parseJSON(jQuery('#addcc_condition_options').val());
        }

        if (jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id)).length === 0) {
            return;
        }

        var select = jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id));

        var selected_conditions = select.data('conditions');

        if (typeof selected_conditions === 'string') {
            selected_conditions = jQuery.parseJSON(selected_conditions);
        }

        select.empty();

        select.append(jQuery('<option value=""></option>'));

        var optgroupRegex = new RegExp('^<optgroup','i');
        var optgroupEndRegex = new RegExp('^</optgroup','i');
        var optionRegex = new RegExp('^<option','i');

        var opt,optgroup,option;
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
                option = jQuery(opt);
                if (selected_conditions.indexOf(option.val()) > -1) {
                    option.prop('selected',true);
                }
                if (optgroup) {
                    optgroup.append(option);
                } else {
                    select.append(option);
                }
            }
        }

        // load selectize
        var conditionSelect = select.selectize({
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


    // unload condition selectize for editor
    var unloadConditionSelect = function(editor_id) {
        if (jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id)).length === 0) {
            return;
        }

        var selected = jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id))[0].selectize.getValue();

        // update selected conditions parameter
        jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id)).data('conditions', JSON.stringify(selected));

        jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id))[0].selectize.destroy();
        jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id)).empty();

        // set selected options
        var option;
        for (var i = 0; i < selected.length; i++) {
            option = jQuery('<option />');
            option.val(selected[i]);
            option.attr('selected', true);
            jQuery('.conditions select',jQuery('#ccss_editor_' + editor_id)).append(option);
        }
    }

    if (jQuery('.criticalcss-edit-header').length > 0) {

        var toggleTimeout = {};

        var toggleClickHandler = function(header,state) {

            var editor_id = jQuery(header).attr('rel');

            var toggleState = parseInt(jQuery('#ccss_editor_' + editor_id).data('toggle-start'));
            if (toggleState === 1) {

                if (state === 1) {

                    if (toggleTimeout[editor_id]) {
                        clearTimeout(toggleTimeout[editor_id]);
                    }

                    // restore
                    jQuery('#ccss_editor_' + editor_id).data('toggle-start','');
                    jQuery(header).on('click', toggleCriticalCSSEditor);

                    jQuery('.loading-editor',header).hide();
                }
            } else {

                // stop listening for clicks
                if (state === 0) {

                    // show loading notice
                    if (!jQuery('#ccss_editor_' + editor_id).is(':visible')) {
                        jQuery('.loading-editor',header).show();
                    }

                    // stop listening for clicks
                    jQuery('#ccss_editor_' + editor_id).data('toggle-start',1);
                    jQuery(header).off('click', toggleCriticalCSSEditor);

                    // restore timeout
                    toggleTimeout[editor_id] = setTimeout(function() {
                        toggleClickHandler(header,1);
                    },3000);
                }
            }

            return toggleState;

        }

        /**
         * Toggle critical CSS editor
         */
        var toggleCriticalCSSEditor = function(e) {

            var header = jQuery(this);
            var editor_id = jQuery(this).attr('rel');

            // prevent multiple fast clicks
            if(e.originalEvent.detail > 1){
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            // stop listening for clicks on header, to prevent overload
            if (toggleClickHandler(header,0) === 1) {
                return false;
            }

            setTimeout(function() {

                // load editor in animation frame
                window.requestAnimationFrame(function RAF() {

                    if (jQuery('#ccss_editor_' + editor_id).is(':visible')) {

                        /**
                         * Destroy advanced editor
                         */
                        if (advancedEditor) {
                            unloadEditor(editor_id);
                        }

                        // unload selectize
                        unloadConditionSelect(editor_id);

                        jQuery('#ccss_editor_' + editor_id).hide();

                    } else {

                        jQuery('#ccss_editor_' + editor_id).show();

                        if (advancedEditor) {
                            loadEditor(editor_id);
                        }

                        // load selectize
                        loadConditionSelect(editor_id);

                    }

                    // restore listening for clicks
                    toggleClickHandler(header,1);

                });
            },10);
        }

        // watch click
        jQuery('.criticalcss-edit-header').on('click', toggleCriticalCSSEditor);

        // delete buttons
        jQuery('.criticalcss-edit-header').each(function(i, header) {
            if (jQuery('.item-delete',jQuery(header)).length > 0) {

                var ccss_id = jQuery(header).attr('rel');

                jQuery('.item-delete',jQuery(header)).on('click', function(e) {

                    e.preventDefault();
                    e.stopPropagation();

                    if (confirm(jQuery(this).data('confirm'),true)) {

                        // create delete form
                        var form = jQuery('<form />');
                        form.attr('method','post');
                        form.attr('action',jQuery('#abtf_settings_form').data('delccss'));

                        var input = jQuery('<input type="hidden" name="id" />');
                        input.val(ccss_id);
                        form.append(input);

                        var input = jQuery('<input type="hidden" name="_wpnonce" />');
                        input.val(jQuery('#_wpnonce').val());
                        form.append(input);

                        jQuery('body').append(form);

                        jQuery(form).submit();
                    }
                });
            }
        })

    }

    /**
     * Advanced Critical CSS editor
     */
    if (jQuery('#abtfcss').length > 0 && parseInt(jQuery('#abtfcss').data('advanced')) === 1) {

        jQuery('.ccss_editor').each(function(i,el) {

            // editor is visible
            if (jQuery(el).is(':visible')) {
                if (advancedEditor) {
                    loadEditor(jQuery('.criticalcss-edit-header',jQuery(el).parents('.menu-item').first()).attr('rel'));
                }
            }
        });

/*
        window.abtfcssToggle = function(obj) {
            if (jQuery('.CodeMirror').hasClass('large')) {
                jQuery(obj).html('[+] Large Editor');
            } else {
                jQuery(obj).html('[-] Small Editor');
            }

            jQuery('.CodeMirror').toggleClass('large');
            //window.abtfcss.refresh();
        };*/
    }
});