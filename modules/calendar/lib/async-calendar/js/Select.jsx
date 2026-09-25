import { getSelect2LanguageParameters } from "./Functions";

const $ = jQuery;

export default function Select(props) {
    const selectRef = React.useRef(null);

    const getAllowClearProp = () => {
        if (typeof props.allowClear !== 'undefined' && props.allowClear !== null) {
            return props.allowClear;
        }

        return true;
    }

    const initSelect2 = () => {
        let params = {
            placeholder: props.placeholder || false,
            language: getSelect2LanguageParameters(),
            tags: true,
            allowClear: getAllowClearProp()
        };

        if (props.ajaxUrl && props.ajaxAction) {
            params.ajax = {
                delay: 250,
                url: props.ajaxUrl,
                dataType: 'json',
                data: function (params) {
                    let args = {
                        q: params.term,
                        action: props.ajaxAction,
                        nonce: props.nonce,
                    }

                    if (props.ajaxArgs) {
                        for (const arg in props.ajaxArgs) {
                            if (props.ajaxArgs.hasOwnProperty(arg)) {
                                args[arg] = props.ajaxArgs[arg];
                            }
                        }
                    }

                    return args;
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                }
            };
        }

        $(selectRef.current).pp_select2(params)
            .on('select2:select', (e) => {
                if (typeof props.onSelect === 'function') {
                    props.onSelect(
                        e,
                        selectRef.current,
                        $(selectRef.current).pp_select2('data')
                    );
                }
            })
            .on('select2:clear', (e) => {
                if (typeof props.onClear === 'function') {
                    props.onClear(
                        e,
                        selectRef.current
                    );
                }
            });

        return () => {
            $(selectRef.current).pp_select2('destroy');
        }
    };

    const blankOption = () => {
        if (props.placeholder) {
            return <option value="">{props.placeholder}</option>
        }

        return <></>;
    };

    React.useEffect(initSelect2, []);

    let options;

    if (props.options) {
        options = props.options.map(option => {
            return <option key={`select-option-${option.value}`} value={option.value}>{option.text}</option>
        });
    }

    let className = 'pp_select2';
    if (props.className) {
        className += ' ' + props.className;
    }

    className += props.metadata ? 'pp-calendar-form-metafied ' + props.post_types : '';
    return (
            <select className={className}
                    defaultValue={props.value}
                    type="select"
                    name={props.name}
                    id={props.id}
                    multiple={props.multiple}
                    ref={selectRef}>
                {blankOption()}
                {options}
            </select>
    )
}
