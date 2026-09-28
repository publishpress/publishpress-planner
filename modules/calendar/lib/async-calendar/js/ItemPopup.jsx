import {callAjaxAction} from "./Functions";
import DateTimeField from "./fields/DateTimeField";
import AuthorsField from "./fields/AuthorsField";
import PostTypeField from "./fields/PostTypeField";
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
import DOMPurify from "dompurify";


const $ = jQuery;

export default function ItemPopup(props) {
    if (!props.data) {
        return <></>;
    }

    if (!props.target.current) {
        return <></>;
    }

    const offsetX = 10;
    const offsetWidth = 180;
    const targetPosition = $(props.target.current).position();
    const targetOffset = $(props.target.current).offset();
    const targetWidth = $(props.target.current).width();
    const popupWidth = 380;

    const isWiderThanParentWidth = () => {
        return (targetOffset.left + popupWidth + offsetX + offsetWidth) >= $(document).width();
    }

    const getPositionOnRightSide = () => {
        return targetPosition.left + targetWidth + offsetX;
    }

    const getPositionOnLeftSide = () => {
        return targetPosition.left - (offsetX * 2.5) - popupWidth;
    }

    const closePopup = () => {
        $(document).trigger('publishpress_calendar:close_popup');
    }

    const positionTop = targetPosition.top;
    const positionLeft = isWiderThanParentWidth() ? getPositionOnLeftSide() : getPositionOnRightSide();

    const getFieldRows = () => {
        const fieldRows = [];

        let dataProperty;
        let field;

        for (const dataPropertyName in props.data.fields) {
            if (!props.data.fields.hasOwnProperty(dataPropertyName)) {
                continue;
            }

            dataProperty = props.data.fields[dataPropertyName];

            switch (dataProperty.type) {
                case 'date':
                    field = <DateTimeField value={dataProperty.value} valueString={dataProperty.valueString || null}
                                           isEditing={false}/>;
                    break;

                case 'time':
                    field = <TimeField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'authors':
                    field = <AuthorsField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'select':
                    field = <SelectField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'type':
                    field = <PostTypeField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'status':
                    field = <PostStatusField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'taxonomy':
                    field = <TaxonomyField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'checkbox':
                    field = <CheckboxField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'location':
                    field = <LocationField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'paragraph':
                    field = <TextArea value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'text':
                    field = <TextField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'user':
                    field = <UserField value={dataProperty.value} isEditing={false}/>;
                    break;

                case 'number':
                    field = <NumberField value={dataProperty.value} isEditing={false}/>;
                    break;

                default:
                    field = null;
                    break;
            }

            fieldRows.push(
                <tr key={`field-rows-popup-${fieldRows.length}`}>
                    <th>{dataProperty.label}:</th>
                    <td>{field}</td>
                </tr>
            );
        }

        return fieldRows;
    };

    const handleOnClick = (e, linkData) => {
        e.preventDefault();

        callAjaxAction(linkData.action, linkData.args, props.ajaxUrl).then((result) => {
            props.onItemActionClickCallback(linkData.action, props.id, result);
        });
    }


    const getPostLinks = () => {
        const links = [];
        let linkData;

        for (const linkName in props.data.links) {
            if (!props.data.links.hasOwnProperty(linkName)) {
                continue;
            }

            linkData = props.data.links[linkName];

            if (linkData.url) {
                links.push(
                    <a key={`links-popup-${links.length}`} href={linkData.url}>{linkData.label}</a>
                );
            } else if (linkData.action) {
                links.push(
                    <button type="button" className="publishpress-calendar-popup-action-button" key={`links-popup-${links.length}`} onClick={(e) => handleOnClick(e, linkData)}>{linkData.label}</button>
                );
            }

            links.push(<span key={`links-popup-${links.length}`}>|</span>);
        }

        links.pop();

        return links;
    }

    const sanitizedTitle = DOMPurify.sanitize(props.title);

    return (
        <div className="publishpress-calendar-popup" style={{top: positionTop, left: positionLeft}}>
            <div className="publishpress-calendar-popup-title" style={{backgroundColor: props.color}}>
                {props.icon &&
                <span className={'dashicons ' + props.icon + ' publishpress-calendar-icon'}/>
                }
                <span dangerouslySetInnerHTML={{__html: sanitizedTitle}}></span>

                <button type="button" className={'dashicons dashicons-no publishpress-calendar-popup-close'}
                        aria-label={props.strings.close} onClick={closePopup}/>
            </div>
            <table>
                <tbody>
                {getFieldRows()}
                </tbody>
            </table>
            <hr/>
            <div className="publishpress-calendar-popup-links">
                {getPostLinks()}
            </div>
        </div>
    )
}
