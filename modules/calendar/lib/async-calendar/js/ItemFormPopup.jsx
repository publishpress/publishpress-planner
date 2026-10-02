import {callAjaxAction, callAjaxPostAction, getDateAsStringInWpFormat, getPostLinksElement} from "./Functions";
import Select from "./Select";
import DateTimeField from "./fields/DateTimeField";
import AuthorsField from "./fields/AuthorsField";
import SelectField from "./fields/SelectField";
import PostStatusField from "./fields/PostStatusField";
import TaxonomyField from "./fields/TaxonomyField";
import CheckboxField from "./fields/CheckboxField";
import LocationField from "./fields/LocationField";
import TextArea from "./fields/TextArea";
import TextField from "./fields/TextField";
import UserField from "./fields/UserField";
import NumberField from "./fields/NumberField";
import TimeField from "./fields/TimeField";
import MetaField from "./fields/MetaField";

const $ = jQuery;

export default function ItemFormPopup(props) {
    const [postType, setPostType] = React.useState(props.postTypes[0].value);
    const [fields, setFields] = React.useState([]);
    const [isLoading, setIsLoading] = React.useState(false);
    const [savingLink, setSavingLink] = React.useState(false);
    const [errorMessage, setErrorMessage] = React.useState();

    const didMount = () => {
        resetGlobalFormFieldData()

        setPostType(props.postTypes[0].value);

        setDefaultValueForFields();
        activateFixForScreenLockerSize();

        return didUnmount;
    }

    const didUnmount = () => {
        deactivateFixForScreenLockerSize();

        resetGlobalFormFieldData();
    }

    const activateFixForScreenLockerSize = () => {
        $('#wpwrap').css('overflow', 'hidden');
    }

    const deactivateFixForScreenLockerSize = () => {
        $('#wpwrap').css('overflow', 'auto');
    }

    const setDefaultValueForFields = () => {
        updateGlobalFormFieldData('post_type', props.postTypes[0].value);
        updateGlobalFormFieldData('status', 'draft');
    }

    const getFormTableFieldRows = () => {
        const fieldRows = [];

        let dataProperty;
        let field;
        let fieldId;
        let placeholder;

        for (const dataPropertyName in fields) {
            if (!fields.hasOwnProperty(dataPropertyName)) {
                continue;
            }

            fieldId = 'publishpress-calendar-field-' + dataPropertyName;

            dataProperty = fields[dataPropertyName];
            placeholder = dataProperty.placeholder ? dataProperty.placeholder : null;

            switch (dataProperty.type) {
                case 'date':
                    field = <DateTimeField value={dataProperty.value}
                                           isEditing={true}
                                           id={fieldId}
                                           onChange={(e, value) => {
                                               updateGlobalFormFieldData(dataPropertyName, value);
                                           }}/>;
                    break;

                case 'authors':
                    field = <AuthorsField value={dataProperty.value}
                                          isEditing={true}
                                          name={dataPropertyName}
                                          id={fieldId}
                                          nonce={props.nonce}
                                          ajaxUrl={props.ajaxUrl}
                                          ajaxArgs={dataProperty.ajaxArgs}
                                          metadata = {dataProperty.metadata ? true : false}
                                          post_types = {dataProperty.post_types ? dataProperty.post_types : ''}
                                          multiple={dataProperty.metadata ? dataProperty.multiple : props.allowAddingMultipleAuthors}
                                          onSelect={(e, elem, data) => {
                                              let values = [];
                                              for (let i = 0; i < data.length; i++) {
                                                  values.push(data[i].id);
                                              }

                                              updateGlobalFormFieldData(dataPropertyName, values);
                                          }}
                                          onClear={(e, elem) => {
                                              updateGlobalFormFieldData(dataPropertyName, null);
                                          }}/>;
                    break;

                case 'select':
                    field = <SelectField value={dataProperty.value}
                                          isEditing={true}
                                          name={dataPropertyName}
                                          id={fieldId}
                                          nonce={props.nonce}
                                          ajaxUrl={props.ajaxUrl}
                                          ajaxAction={dataProperty.ajaxAction}
                                          ajaxArgs={dataProperty.ajaxArgs}
                                          multiple={dataProperty.multiple}
                                          onSelect={(e, elem, data) => {
                                              let values = [];
                                              for (let i = 0; i < data.length; i++) {
                                                  values.push(data[i].id);
                                              }

                                              updateGlobalFormFieldData(dataPropertyName, values);
                                          }}
                                          onClear={(e, elem) => {
                                              updateGlobalFormFieldData(dataPropertyName, null);
                                          }}/>;
                    break;

                case 'status':
                    field = <PostStatusField value={dataProperty.value}
                                             isEditing={true}
                                             id={fieldId}
                                             options={dataProperty.options}
                                             onSelect={(e, elem, data) => {
                                                 let value = null;
                                                 if (data.length > 0) {
                                                     value = data[0].id;
                                                 }

                                                 updateGlobalFormFieldData(dataPropertyName, value);
                                             }}
                                             onClear={(e, elem) => {
                                                 updateGlobalFormFieldData(dataPropertyName, null);
                                             }}/>;
                    break;

                case 'taxonomy':
                    field = <TaxonomyField value={dataProperty.value}
                                           isEditing={true}
                                           id={fieldId}
                                           taxonomy={dataProperty.taxonomy}
                                           nonce={props.nonce}
                                           ajaxUrl={props.ajaxUrl}
                                           multiple={true}
                                           onSelect={(e, elem, data) => {
                                               let values = [];
                                               for (let i = 0; i < data.length; i++) {
                                                   values.push(data[i].id);
                                               }

                                               updateGlobalFormFieldData(dataPropertyName, values);
                                           }}
                                           onClear={(e, elem) => {
                                               updateGlobalFormFieldData(dataPropertyName, null);
                                           }}/>;
                    break;

                case 'checkbox':
                    field = <CheckboxField
                        isEditing={true}
                        id={fieldId}
                        value={dataProperty.value}/>;
                    break;

                case 'location':
                    field = <LocationField value={dataProperty.value}
                                           isEditing={true}
                                           id={fieldId}
                                           onChange={(e, value) => {
                                               updateGlobalFormFieldData(dataPropertyName, value);
                                           }}/>;
                    break;

                case 'html':
                    field = <TextArea value={dataProperty.value}
                                      name={dataPropertyName}
                                      metadata = {dataProperty.metadata ? true : false}
                                      post_types = {dataProperty.post_types ? dataProperty.post_types : ''}
                                      isEditing={true}
                                      id={fieldId}
                                      onChange={(e, value) => {
                                          updateGlobalFormFieldData(dataPropertyName, value);
                                      }}/>;
                    break;

                case 'text':
                    field = <TextField value={dataProperty.value}
                                       isEditing={true}
                                       id={fieldId}
                                       placeholder={placeholder}
                                       onChange={(e, value) => {
                                           updateGlobalFormFieldData(dataPropertyName, value);
                                       }}/>;
                    break;

                case 'user':
                    field = <UserField value={dataProperty.value}
                                       isEditing={true}
                                       id={fieldId}/>;
                    break;

                case 'number':
                    field = <NumberField value={dataProperty.value}
                                         isEditing={true}
                                         id={fieldId}
                                         onChange={(e, value) => {
                                             updateGlobalFormFieldData(dataPropertyName, value);
                                         }}/>;
                    break;

                case 'time':
                    field = <TimeField value={dataProperty.value}
                                       isEditing={true}
                                       id={fieldId}
                                       placeholder={placeholder}
                                       onChange={(e, value) => {
                                           updateGlobalFormFieldData(dataPropertyName, value);
                                       }}/>;
                    break;

                case 'metafield':
                    dataProperty.isEditing = true;
                    field = MetaField(dataProperty);
                    break;

                default:
                    field = null;
                    break;
            }

            fieldRows.push(
                <tr key={`field-rows-${fieldRows.length}`}>
                    <th><label htmlFor={fieldId}>{dataProperty.label}:</label></th>
                    <td>{field}</td>
                </tr>
            );
        }

        return fieldRows;
    };

    // We are using a global var because the states are async and we were having a hard time to make all
    // fields work together updating the same state.
    const getGlobalFormFieldData = (name) => {
        if (typeof window.publishpressCalendarGlobalData === 'undefined') {
            window.publishpressCalendarGlobalData = {};
        }

        if (typeof window.publishpressCalendarGlobalData.formFieldsData === 'undefined') {
            window.publishpressCalendarGlobalData.formFieldsData = {};
        }

        if (window.publishpressCalendarGlobalData.formFieldsData.hasOwnProperty(name)) {
            return window.publishpressCalendarGlobalData.formFieldsData[name];
        }

        return null;
    }

    const setGlobalFormFieldData = (name, value) => {
        getGlobalFormFieldData(name);

        window.publishpressCalendarGlobalData.formFieldsData[name] = value;
    }

    const resetGlobalFormFieldData = () => {
        if (typeof window.publishpressCalendarGlobalData !== 'undefined'
            && typeof window.publishpressCalendarGlobalData.formFieldsData !== 'undefined') {
            window.publishpressCalendarGlobalData.formFieldsData = [];
        }
    }

    const updateGlobalFormFieldData = (fieldName, value) => {
        setGlobalFormFieldData(fieldName, value);
    }

    const getFormDataForThePostRequest = () => {
        let formData = new FormData;

        formData.append('date', getDateAsStringInWpFormat(props.date));
        formData.append('nonce', props.nonce);

        for (const fieldName in window.publishpressCalendarGlobalData.formFieldsData) {
            if (window.publishpressCalendarGlobalData.formFieldsData.hasOwnProperty(fieldName)) {
                formData.append(fieldName, window.publishpressCalendarGlobalData.formFieldsData[fieldName]);
            }
        }

        //add metafield
        let field_name  = '',
            field_value = '',
            field_type  = '',
            skip_field  = false;
        document.querySelectorAll('.pp-calendar-form-metafied-input').forEach(function (metafield) {
            field_name  = metafield.getAttribute('name');
            field_type  = metafield.getAttribute('type');
            field_value = metafield.value;
            skip_field  = false;
            if (field_type === 'checkbox' && !metafield.checked) {
                skip_field  = true;
            }
            if (metafield.classList.contains('pp_editorial_meta_multi_select2')) {
                skip_field            = true;
                var selected_options = metafield.selectedOptions
                var selected_key = '';
                for (selected_key in selected_options) {
                    if (!selected_options.hasOwnProperty(selected_key)) {
                        continue;
                    }
                    formData.append(field_name, selected_options[selected_key].value);
                }
            }

            if (!skip_field) {
                formData.append(field_name, field_value);
            }
        });

        return formData;
    }

    const handleLinkOnClick = (e, linkData) => {
        e.preventDefault();

        setIsLoading(true);
        setSavingLink(linkData.id);
        setErrorMessage(null);

        callAjaxPostAction(linkData.action, linkData.args, props.ajaxUrl, getFormDataForThePostRequest()).then((result) => {
            if (linkData.id === 'create') {
                setIsLoading(false);
                setSavingLink(null);

                if (result.status === 'success') {
                    if (props.onCloseCallback) {
                        props.onCloseCallback();
                    }
                } else {
                    setErrorMessage(result.message);
                }
            } else if (linkData.id === 'edit') {
                if (result.status === 'success') {
                    window.location.href = result.data.link;
                } else {
                    setIsLoading(false);
                    setSavingLink(null);
                    setErrorMessage(result.message);
                }
            }
        });
    }

    const getFooterLinks = () => {
        const formLinks = [
            {
                'id': 'create',
                'className': 'button-primary calendar-create-btn',
                'label': props.strings.save,
                'labelLoading': props.strings.saving,
                'action': 'publishpress_calendar_create_item'
            },
            {
                'id': 'edit',
                'className': 'button-secondary calendar-edit-btn',
                'label': props.strings.saveAndEdit,
                'labelLoading': props.strings.saving,
                'action': 'publishpress_calendar_create_item'
            }
        ];

        const links = [];
        let linkData;

        for (const linkName in formLinks) {
            if (!formLinks.hasOwnProperty(linkName)) {
                continue;
            }

            linkData = formLinks[linkName];

            if (savingLink === linkData.id) {
                links.push(<span key={linkData.id}>{linkData.labelLoading}</span>);
            } else {
                links.push(getPostLinksElement(linkData, handleLinkOnClick));
            }
            links.push(<span key={`link-separator-${links.length}`}></span>);
        }

        links.pop();

        return links;
    }

    const handleOnSelectPostTypeField = (e) => {
        let $target = $(e.target);

        const postType = $target.pp_select2('data')[0].id;

        setPostType(postType);

        updateGlobalFormFieldData('post_type', postType);
    }

    const getFormPopupTitle = () => {
        let title;
        if (props.postId) {
            title = '';
        } else {
            title = props.strings.addContentFor;
            title = title.replace('%s', date_i18n(props.dateFormat, props.date));
        }

        return title;
    }

    const getPostTypeNameBySlug = (postTypeSlug) => {
        for (let postTypeObj of props.postTypes) {
            if (postTypeObj.value === postTypeSlug) {
                return postTypeObj.text;
            }
        }

        return props.strings.postTypeNotFound;
    }

    const fetchFieldsForSelectedPostType = () => {
        setIsLoading(true);

        const args = {
            nonce: props.nonce,
            postType: getGlobalFormFieldData('post_type'),
            date: getDateAsStringInWpFormat(props.date)
        };

        setFields(null);

        callAjaxAction(props.actionGetPostTypeFields, args, props.ajaxUrl)
            .then((result) => {
                setFields(result.fields);
            })
            .then(() => {
                setFocusOnTitleField();

                setIsLoading(false);
            });
    }

    const setFocusOnTitleField = () => {
        setTimeout(() => {
            $('.publishpress-calendar-popup-form input').first().focus();
        }, 500);
    }

    React.useEffect(didMount, []);
    React.useEffect(fetchFieldsForSelectedPostType, [postType]);

    const fieldTableRows = getFormTableFieldRows();

    return (
        <>
            <div className={'publishpress-calendar-popup-screen-lock'}/>
            <div className={'publishpress-calendar-popup publishpress-calendar-popup-form'}>
                <div className={'publishpress-calendar-popup-title'}>
                    {getFormPopupTitle()}
                    <button type="button" className={'dashicons dashicons-no publishpress-calendar-popup-close'}
                            aria-label={props.strings.close} onClick={props.onCloseCallback}/>
                </div>
                <hr/>
                <table>
                    <tbody>
                    {props.postTypes.length > 1 &&
                    <tr>
                        <th><label>{props.strings.postType}</label></th>
                        <td>
                            <Select
                                options={props.postTypes}
                                allowClear={false}
                                onSelect={handleOnSelectPostTypeField}
                            />
                        </td>
                    </tr>
                    }

                    {props.postTypes.length === 1 &&
                    <tr>
                        <th><label>{props.strings.postType}</label></th>
                        <td>{getPostTypeNameBySlug(postType)}</td>
                    </tr>
                    }

                    {fieldTableRows.length > 0 &&
                    fieldTableRows
                    }
                    </tbody>
                </table>


                {fieldTableRows.length === 0 &&
                <div
                    className={'publishpress-calendar-popup-loading-fields'}>{props.strings.pleaseWaitLoadingFormFields}</div>
                }

                {errorMessage &&
                <div className={'publishpress-calendar-popup-error-message'}>
                    <span className={'dashicons dashicons-warning'}/>
                    {errorMessage}
                </div>
                }

                <hr className={'publishpress-calendar-popup-links-hr'}/>
                <div className="publishpress-calendar-popup-links">
                    {getFooterLinks()}
                    {isLoading &&
                    <span className={'dashicons dashicons-update-alt publishpress-spinner'}/>
                    }
                </div>
            </div>
        </>
    )
}
