/**
 * FP Finance Hub - Form Validation
 * 
 * Client-side validation and error display
 */

(function($) {
    'use strict';

    const FPFormValidation = {
        
        rules: {
            required: function(value) {
                return value && value.trim().length > 0;
            },
            email: function(value) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(value);
            },
            min: function(value, min) {
                return value.length >= min;
            },
            max: function(value, max) {
                return value.length <= max;
            },
            numeric: function(value) {
                return !isNaN(value) && !isNaN(parseFloat(value));
            },
            currency: function(value) {
                const re = /^\d+(\.\d{1,2})?$/;
                return re.test(value);
            }
        },

        /**
         * Validate form
         */
        validateForm: function($form) {
            let isValid = true;
            const errors = [];

            $form.find('[data-validate]').each(function() {
                const $field = $(this);
                const validateRules = $field.data('validate').split('|');
                const value = $field.val();
                const fieldName = $field.attr('name') || $field.attr('id');

                // Clear previous errors
                $field.removeClass('fp-fh-input-error');
                $field.siblings('.fp-fh-form-error').remove();

                // Check each rule
                for (let i = 0; i < validateRules.length; i++) {
                    const rule = validateRules[i].split(':');
                    const ruleName = rule[0];
                    const ruleValue = rule[1];

                    if (this.rules[ruleName]) {
                        const result = this.rules[ruleName](value, ruleValue);
                        if (!result) {
                            isValid = false;
                            this.showError($field, this.getErrorMessage(ruleName, ruleValue));
                            errors.push({
                                field: fieldName,
                                rule: ruleName,
                                message: this.getErrorMessage(ruleName, ruleValue)
                            });
                            break;
                        }
                    }
                }
            }.bind(this));

            return {
                valid: isValid,
                errors: errors
            };
        },

        /**
         * Show error
         */
        showError: function($field, message) {
            $field.addClass('fp-fh-input-error');
            const $error = $('<div class="fp-fh-form-error">' + message + '</div>');
            $field.after($error);
        },

        /**
         * Get error message
         */
        getErrorMessage: function(rule, value) {
            const messages = {
                required: 'Questo campo è obbligatorio.',
                email: 'Inserisci un indirizzo email valido.',
                min: 'Questo campo deve contenere almeno ' + value + ' caratteri.',
                max: 'Questo campo non può contenere più di ' + value + ' caratteri.',
                numeric: 'Inserisci un numero valido.',
                currency: 'Inserisci un importo valido.'
            };
            return messages[rule] || 'Errore di validazione.';
        },

        /**
         * Initialize form validation
         */
        init: function() {
            // Validate on submit
            $(document).on('submit', 'form[data-validate-form]', function(e) {
                e.preventDefault();
                const $form = $(this);
                const validation = FPFormValidation.validateForm($form);

                if (!validation.valid) {
                    FPFinanceHub.showNotice('error', 'Correggi gli errori nel modulo.');
                    return false;
                }

                // If valid, submit form normally or via AJAX
                if ($form.data('ajax')) {
                    // Handle via AJAX (handled by admin.js)
                    return true;
                } else {
                    // Submit normally
                    $form.off('submit').submit();
                }
            });

            // Real-time validation on blur
            $(document).on('blur', '[data-validate]', function() {
                const $field = $(this);
                const validateRules = $field.data('validate').split('|');
                const value = $field.val();

                $field.removeClass('fp-fh-input-error');
                $field.siblings('.fp-fh-form-error').remove();

                for (let i = 0; i < validateRules.length; i++) {
                    const rule = validateRules[i].split(':');
                    const ruleName = rule[0];
                    const ruleValue = rule[1];

                    if (FPFormValidation.rules[ruleName]) {
                        const result = FPFormValidation.rules[ruleName](value, ruleValue);
                        if (!result) {
                            FPFormValidation.showError($field, FPFormValidation.getErrorMessage(ruleName, ruleValue));
                            break;
                        }
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FPFormValidation.init();
    });

    // Expose globally
    window.FPFormValidation = FPFormValidation;

})(jQuery);
