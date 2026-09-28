import DOMPurify from "dompurify";
import { getSelect2LanguageParameters } from "../Functions";
const $ = jQuery;

export default function MetaField(props) {
    
    const editField = () => {

        if ($('.pp_editorial_single_select2').length > 0) {
            $('.pp_editorial_single_select2').pp_select2(
              {
                allowClear: true,
                language: getSelect2LanguageParameters(),
                placeholder: function(){
                  $(this).data('placeholder');
                }
              }
            );
          }
        
          if ($('.pp_editorial_meta_multi_select2').length > 0) {
            $('.pp_editorial_meta_multi_select2').pp_select2({
              language: getSelect2LanguageParameters(),
              multiple: true
            });
          }
          $('.date-time-pick').each(function () {
            var self = $(this);
            var options = getOptions(self, {
              alwaysSetTime: false,
              controlType: 'select',
              altFieldTimeOnly: false
            });
            self.datetimepicker(options);
          });
          function getOptions (self, custom_options) {
            var default_options = {};
        
            var options = $.extend({}, default_options, custom_options);
            var altFieldName = self.attr('data-alt-field');
        
            if ((!altFieldName) || typeof altFieldName == 'undefined' || altFieldName.length == 0) {
              return options;
            }
        
            return $.extend({}, options, {
              altField: 'input[name="'+ altFieldName +'"]',
              altFormat: self.attr('data-alt-format'),
            });
          }

        const sanitizedHTML = DOMPurify.sanitize(props.html);
        
        return (
            <div
                dangerouslySetInnerHTML={{ __html: sanitizedHTML }}>
            </div>
        )
    }

    const viewField = () => {
        return (
            <span id={props.id}>{props.value}</span>
        );
    }

    return props.isEditing ? editField() : viewField();
}
